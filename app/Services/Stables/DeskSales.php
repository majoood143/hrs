<?php

namespace App\Services\Stables;

use App\Enums\PaymentGateway;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\PosPayment;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableExtra;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Models\User;
use App\Services\Orders\OrderRefundService;
use App\Services\Orders\PriceBreakdown;
use App\Services\Payments\OrderPaymentService;
use App\Support\PhoneNumber;
use App\Support\StablePos;
use Illuminate\Support\Facades\DB;

/**
 * Sales at a stable's desk (the POS, see Support\StablePos). A sale is an ordinary booking or
 * package order made through CreateStableBooking / StablePackages, so places, packages, statements,
 * refunds and notifications all work as for the website. What differs: no service fee (we take the
 * commission only), the stable holds the money (`collected_by = stable`, so its statement owes us the
 * commission), and the payments are recorded here, one row per method, then the order is marked paid
 * through OrderPaymentService like a gateway payment (which confirms the booking or starts the
 * package and sends the usual messages). The `link` method leaves it unpaid with a payment link.
 */
class DeskSales
{
    public function __construct(
        private readonly CreateStableBooking $bookings,
        private readonly StablePackages $packages,
        private readonly StableBookingPricing $pricing,
        private readonly OrderPaymentService $payments,
    ) {}

    /** What a sale would cost at the desk. @throws BookingUnavailable */
    public function quote(Stable $stable, ?BookingSlot $slot, ?StablePackage $package, int $riders, ?string $promoCode, ?string $phone, array $extras = []): PriceBreakdown
    {
        $promo = $this->pricing->promo($promoCode, $stable, PhoneNumber::normalize($phone));

        return $package
            ? $this->pricing->quotePackage($package, $promo, withFee: false)
            : $this->pricing->quote($slot->offering, $riders, $stable, $promo, withFee: false, extras: StableExtra::totalBaisa(StableExtra::lines($slot->offering, $riders, $extras)));
    }

    /**
     * @param  list<string>  $riders  the riders' names
     * @param  array{name: string, phone?: ?string, email?: ?string}  $customer
     * @param  ?list<array{method: string, amount: string|float, reference?: ?string}>  $payments  null = a payment link
     * @param  array<int, int>  $extras  extra id => quantity
     *
     * @throws BookingUnavailable
     */
    public function sellSession(Stable $stable, BookingSlot $slot, array $riders, array $customer, ?array $payments, ?string $promoCode, User $by, array $extras = []): StableBooking
    {
        $this->assertOpen($stable);
        abort_unless((int) $slot->stable_id === (int) $stable->getKey(), 404);

        $phone = PhoneNumber::normalize($customer['phone'] ?? null);
        $existing = $phone ? Customer::query()->where('phone', $phone)->first() : null;
        $total = $this->quote($stable, $slot, null, count($riders), $promoCode, $phone, $extras)->total();
        $this->assertPayments($stable, $payments, $total);

        $booking = $this->bookings->handle($slot, array_map(fn (string $name) => ['name' => trim($name)], $riders), [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? $existing?->email,
            'phone' => $phone,
            'locale' => $existing?->locale ?? app()->getLocale(),
            'customer_id' => $existing?->getKey(),
        ], $payments === null ? CreateStableBooking::ONLINE : CreateStableBooking::DESK, promoCode: $promoCode, atDesk: true, extras: $extras);

        $this->settle($booking->order, $payments, $by);

        return $booking->refresh();
    }

    /**
     * A package needs the customer's account (it is used from their bookings): it is made for the
     * phone if there is none yet, and becomes theirs when they first sign in.
     *
     * @param  array{name: string, phone: string, email?: ?string}  $customer
     * @param  ?list<array{method: string, amount: string|float, reference?: ?string}>  $payments  null = a payment link
     *
     * @throws BookingUnavailable
     */
    public function sellPackage(Stable $stable, StablePackage $package, array $customer, ?array $payments, ?string $promoCode, User $by): StablePackagePurchase
    {
        $this->assertOpen($stable);
        abort_unless((int) $package->stable_id === (int) $stable->getKey(), 404);

        $phone = PhoneNumber::normalize($customer['phone']);
        $total = $this->quote($stable, null, $package, 1, $promoCode, $phone)->total();
        $this->assertPayments($stable, $payments, $total);

        $account = Customer::firstOrCreate(['phone' => $phone], ['locale' => app()->getLocale()]);
        $account->forceFill(['name' => $account->name ?: $customer['name'], 'email' => $account->email ?: ($customer['email'] ?? null)])->save();

        $purchase = $this->packages->purchase($package, $account, $promoCode, $payments === null ? CreateStableBooking::ONLINE : CreateStableBooking::DESK);

        $this->settle($purchase->order, $payments, $by);

        return $purchase->refresh();
    }

    /**
     * Gives a desk sale's money back (the stable hands it over): a session still to come is
     * cancelled too, with the reason sent to the customer (StableBookingActions records the refund);
     * otherwise the refund is recorded on its own (a package then stops: StablePackages::orderRefunded).
     *
     * @throws BookingUnavailable
     */
    public function refund(ServiceOrder $order, string $reason, User $by): ServiceOrder
    {
        if (! $order->isDeskSale() || ! $order->isPaid()) {
            throw new BookingUnavailable(__('stable_pos.errors.not_refundable'));
        }

        $booking = $order->stableBooking;

        if ($booking?->isActive()) {
            app(StableBookingActions::class)->cancelByStable($booking, $reason);
        } else {
            app(OrderRefundService::class)->record($order, app(OrderRefundService::class)->refundable($order), trim($reason), $by->getKey(), reference: CreateStableBooking::DESK);
        }

        return $order->refresh();
    }

    /** @throws BookingUnavailable */
    private function assertOpen(Stable $stable): void
    {
        if (! StablePos::openFor($stable)) {
            throw new BookingUnavailable(__('stable_pos.errors.closed'));
        }
    }

    /**
     * Each method one the desk takes, and the amounts add up to the total exactly (a split sale may
     * mix methods; change for cash is worked out on the phone, only what was kept is recorded).
     *
     * @throws BookingUnavailable
     */
    private function assertPayments(Stable $stable, ?array $payments, int $total): void
    {
        $methods = StablePos::methodsFor($stable);

        if ($payments === null) {
            if (! in_array(StablePos::LINK, $methods, true)) {
                throw new BookingUnavailable(__('stable_pos.errors.method'));
            }

            return;
        }

        if ($total === 0) {
            return;
        }

        foreach ($payments as $payment) {
            if ($payment['method'] === StablePos::LINK || ! in_array($payment['method'], $methods, true)) {
                throw new BookingUnavailable(__('stable_pos.errors.method'));
            }
        }

        $paid = array_sum(array_map(fn (array $p) => (int) round((float) $p['amount'] * 1000), $payments));

        if ($paid !== $total) {
            throw new BookingUnavailable(__('stable_pos.errors.total', ['paid' => PriceBreakdown::toOmr($paid), 'total' => PriceBreakdown::toOmr($total)]));
        }
    }

    /** Records the desk's payments and marks the order paid (a free or link sale has nothing to record). */
    private function settle(ServiceOrder $order, ?array $payments, User $by): void
    {
        $order->forceFill(['desk_sold_by' => $by->getKey()])->save();

        if ($payments === null || ! $order->isPayable()) {
            return;
        }

        // the price moved between the check and the order (a promo code used up meanwhile): undo it
        if (array_sum(array_map(fn (array $p) => (int) round((float) $p['amount'] * 1000), $payments)) !== $order->totalBaisa()) {
            $this->payments->cancelPending($order, 'stable');

            throw new BookingUnavailable(__('stable_pos.errors.changed'));
        }

        DB::transaction(function () use ($order, $payments, $by): void {
            foreach ($payments as $payment) {
                PosPayment::query()->create([
                    'service_order_id' => $order->getKey(),
                    'method' => $payment['method'],
                    'amount' => PriceBreakdown::toOmr((int) round((float) $payment['amount'] * 1000)),
                    'reference' => filled($payment['reference'] ?? null) ? trim($payment['reference']) : null,
                    'recorded_by' => $by->getKey(),
                ]);
            }
        });

        $this->payments->applyPaid($order, PaymentGateway::Desk, $payments[0]['reference'] ?? null);
    }
}
