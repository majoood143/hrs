<?php

namespace App\Services\Stables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Events\StableBookingConfirmed;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\StableBooking;
use App\Models\StableExtra;
use App\Models\StablePackagePurchase;
use App\Services\Orders\PriceBreakdown;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Books places in a slot. The slot row is locked while its places are counted and the booking is
 * written, so two customers can never both take the last place.
 *
 * The money goes on a service order, like every other paid order:
 * - online: the order waits for payment (the booking holds its places meanwhile, and the expiry
 *   sweep cancels both if it is never paid). It is paid into our merchant account, or into the
 *   stable's own account when the stable chose that and an admin approved its keys;
 * - at the stable: confirmed at once, nothing is collected online;
 * - free (price 0): confirmed at once.
 *
 * From the stable's desk (the POS, `$atDesk`, StablePos): no service fee (we take the commission
 * only), the stable's own payment options and the unpaid-checkout limit do not apply, and a time can
 * be sold until it starts. DESK is paid there and then (StablePos marks it paid); ONLINE is a
 * payment link the stable sends the customer.
 */
class CreateStableBooking
{
    public const ONLINE = 'online';

    public const AT_STABLE = 'at_stable';

    public const PACKAGE = 'package';

    /** Sold and paid at the stable's desk (the POS). */
    public const DESK = 'desk';

    /** Unpaid online checkouts one customer may wait on at once. */
    public const MAX_UNPAID = 3;

    public function __construct(private readonly StableBookingPricing $pricing) {}

    /**
     * @param  list<array<string, mixed>>  $riders  one entry per rider (already validated against the stable's rider fields)
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, locale?: ?string, customer_id?: ?int}  $customer
     * @param  ?string  $promoCode  a promo code typed by the customer (not with a package; online or free only)
     * @param  bool  $atDesk  sold by the stable at its desk (the POS): see the class notes
     *
     * @throws BookingUnavailable
     */
    public function handle(BookingSlot $slot, array $riders, array $customer, string $paymentOption = self::ONLINE, ?int $packagePurchaseId = null, ?string $promoCode = null, bool $atDesk = false, array $extras = []): StableBooking
    {
        $count = count($riders);

        if ($count < 1) {
            throw new BookingUnavailable(__('stable_bookings.errors.no_riders'));
        }

        if ($paymentOption === self::DESK && ! $atDesk) {
            throw new BookingUnavailable(__('stable_bookings.errors.payment_option'));
        }

        [$booking] = DB::transaction(function () use ($slot, $riders, $count, $customer, $paymentOption, $packagePurchaseId, $promoCode, $atDesk, $extras): array {
            /** @var BookingSlot|null $locked */
            $locked = BookingSlot::query()->whereKey($slot->getKey())->lockForUpdate()->first();

            if (! $locked) {
                throw new BookingUnavailable(__('stable_bookings.errors.slot_gone'));
            }

            $locked->load(['offering', 'stable']);
            $this->assertBookable($locked, $count, $atDesk);

            $offering = $locked->offering;
            $stable = $locked->stable;
            $purchase = $paymentOption === self::PACKAGE ? $this->lockPackage($packagePurchaseId, $customer, $locked, $count) : null;
            // sessions from a package were paid for when it was bought: this booking itself costs nothing
            $promo = $purchase ? null : $this->pricing->promo($promoCode, $stable, $customer['phone'] ?? null, lock: true);
            $lines = StableExtra::lines($offering, $count, $extras);

            // a package's session has a free order: extras would need a payment of their own
            if ($purchase && $lines) {
                throw new BookingUnavailable(__('stable_extras.errors.with_package'));
            }

            $quote = $purchase ? $this->pricing->quotePrice(0, $stable) : $this->pricing->quote($offering, $count, $stable, $promo, withFee: ! $atDesk, extras: StableExtra::totalBaisa($lines));

            // the stable collects at its desk what it likes: a code is taken off an online payment only
            if ($promo && ! $quote->isFree() && $paymentOption === self::AT_STABLE) {
                throw new BookingUnavailable(__('promo_codes.errors.online_only'));
            }

            if (! $atDesk && ! $purchase && ! $quote->isFree() && ! in_array($paymentOption, $stable->bookingSettings()->paymentOptions(), true)) {
                throw new BookingUnavailable(__('stable_bookings.errors.payment_option'));
            }

            $free = $quote->isFree();
            $atStable = ! $free && $paymentOption === self::AT_STABLE;
            $desk = ! $free && $paymentOption === self::DESK;

            if (! $free && ! $atStable && ! $atDesk) {
                $this->assertFewUnpaid($customer['customer_id'] ?? null);
            }
            $account = ! $free && ! $atStable && ! $desk ? $stable->activePaymentAccount() : null;

            $order = ServiceOrder::create([
                'stable_id' => $stable->getKey(),
                'stable_payment_account_id' => $account?->getKey(),
                'collected_by' => $free ? null : (($atStable || $desk || $account) ? 'stable' : 'platform'),
                'customer_id' => $customer['customer_id'] ?? null,
                'customer_name' => $customer['name'] ?? null,
                'customer_email' => $customer['email'] ?? null,
                'customer_phone' => PhoneNumber::normalize($customer['phone'] ?? null),
                'locale' => $customer['locale'] ?? app()->getLocale(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => ($free || $atStable) ? OrderStatus::New : OrderStatus::PendingPayment,
                'payment_status' => match (true) {
                    $free => PaymentStatus::Free,
                    $atStable => PaymentStatus::OnSite,
                    default => PaymentStatus::Pending,
                },
            ] + $this->pricing->orderAttributes($quote, $stable, $promo));

            $booking = StableBooking::create([
                'stable_id' => $stable->getKey(),
                'booking_slot_id' => $locked->getKey(),
                'stable_offering_id' => $offering->getKey(),
                'service_order_id' => $order->getKey(),
                'customer_id' => $customer['customer_id'] ?? null,
                'riders' => $count,
                'riders_data' => array_values($riders),
                'payment_option' => $purchase ? self::PACKAGE : ($free ? 'free' : $paymentOption),
                'stable_package_purchase_id' => $purchase?->getKey(),
                'status' => ($free || $atStable) ? StableBookingStatus::Confirmed : StableBookingStatus::Pending,
                'confirmed_at' => ($free || $atStable) ? now() : null,
            ]);

            foreach ($lines as $line) {
                $booking->extras()->create([
                    'stable_extra_id' => $line['extra']->getKey(),
                    'en_name' => $line['extra']->en_name,
                    'ar_name' => $line['extra']->ar_name,
                    'unit_price' => $line['extra']->price,
                    'quantity' => $line['quantity'],
                    'amount' => PriceBreakdown::toOmr($line['amount']),
                ]);
            }

            $order->recordEvent('created', __('orders.events.created'), ['booking' => $booking->reference]);

            return [$booking, $order];
        });

        // nothing to wait for: the customer and the stable are told now (a paid one is confirmed
        // when its payment comes in, through ServiceOrderReceived)
        if ($booking->status === StableBookingStatus::Confirmed) {
            StableBookingConfirmed::dispatch($booking);
        }

        return $booking;
    }

    /**
     * The customer's package, locked while its sessions are counted: theirs, for this service, still
     * valid on the session's day, with enough sessions left for every rider.
     *
     * @throws BookingUnavailable
     */
    private function lockPackage(?int $id, array $customer, BookingSlot $slot, int $riders): StablePackagePurchase
    {
        $purchase = $id ? StablePackagePurchase::query()->whereKey($id)->lockForUpdate()->first() : null;

        if (! $purchase
            || empty($customer['customer_id'])
            || (int) $purchase->customer_id !== (int) $customer['customer_id']
            || (int) $purchase->stable_offering_id !== (int) $slot->stable_offering_id
            || ! $purchase->isUsable()) {
            throw new BookingUnavailable(__('stable_packages.errors.not_usable'));
        }

        if ($slot->startsAt()->greaterThan($purchase->expires_at)) {
            throw new BookingUnavailable(__('stable_packages.errors.expires_before', ['date' => $purchase->expires_at->toDateString()]));
        }

        if ($purchase->sessionsLeft() < $riders) {
            throw new BookingUnavailable(trans_choice('stable_packages.errors.sessions_left', $purchase->sessionsLeft(), ['count' => $purchase->sessionsLeft()]));
        }

        return $purchase;
    }

    /**
     * An unpaid checkout holds its places until it expires, so one customer may wait on only a few
     * at once: nobody can block a stable's times by booking and never paying. Their row is locked,
     * so bookings sent together are counted one after the other.
     *
     * @throws BookingUnavailable
     */
    private function assertFewUnpaid(?int $customerId): void
    {
        if (! $customerId) {
            return;
        }

        Customer::query()->whereKey($customerId)->lockForUpdate()->first();

        $unpaid = StableBooking::query()
            ->where('customer_id', $customerId)
            ->where('status', StableBookingStatus::Pending->value)
            ->count();

        if ($unpaid >= self::MAX_UNPAID) {
            throw new BookingUnavailable(__('stable_bookings.errors.too_many_unpaid', ['count' => self::MAX_UNPAID]));
        }
    }

    /** @throws BookingUnavailable */
    public function assertBookable(BookingSlot $slot, int $riders, bool $atDesk = false): void
    {
        $offering = $slot->offering;
        $stable = $slot->stable;

        if (! $stable?->acceptsBookings() || ! $offering?->is_active || ! $slot->is_open) {
            throw new BookingUnavailable(__('stable_bookings.errors.not_bookable'));
        }

        // the desk sells a time until it starts (the rider is standing there), the website until its cutoff
        if ($atDesk ? $slot->hasStarted() : $slot->bookingClosesAt()->isPast()) {
            throw new BookingUnavailable(__('stable_bookings.errors.closed'));
        }

        if ($riders < $offering->min_riders || $riders > $offering->max_riders) {
            throw new BookingUnavailable(__('stable_bookings.errors.riders_range', ['min' => $offering->min_riders, 'max' => $offering->max_riders]));
        }

        $left = $slot->remainingPlaces();

        if ($left < $riders) {
            throw new BookingUnavailable($left > 0
                ? __('stable_bookings.errors.only_left', ['count' => $left])
                : __('stable_bookings.errors.full'));
        }
    }
}
