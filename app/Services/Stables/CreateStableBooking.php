<?php

namespace App\Services\Stables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Events\StableBookingConfirmed;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Models\StableBooking;
use App\Models\StablePackagePurchase;
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
 */
class CreateStableBooking
{
    public const ONLINE = 'online';

    public const AT_STABLE = 'at_stable';

    public const PACKAGE = 'package';

    public function __construct(private readonly StableBookingPricing $pricing) {}

    /**
     * @param  list<array<string, mixed>>  $riders  one entry per rider (already validated against the stable's rider fields)
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, locale?: ?string, customer_id?: ?int}  $customer
     *
     * @throws BookingUnavailable
     */
    public function handle(BookingSlot $slot, array $riders, array $customer, string $paymentOption = self::ONLINE, ?int $packagePurchaseId = null): StableBooking
    {
        $count = count($riders);

        if ($count < 1) {
            throw new BookingUnavailable(__('stable_bookings.errors.no_riders'));
        }

        [$booking] = DB::transaction(function () use ($slot, $riders, $count, $customer, $paymentOption, $packagePurchaseId): array {
            /** @var BookingSlot|null $locked */
            $locked = BookingSlot::query()->whereKey($slot->getKey())->lockForUpdate()->first();

            if (! $locked) {
                throw new BookingUnavailable(__('stable_bookings.errors.slot_gone'));
            }

            $locked->load(['offering', 'stable']);
            $this->assertBookable($locked, $count);

            $offering = $locked->offering;
            $stable = $locked->stable;
            $purchase = $paymentOption === self::PACKAGE ? $this->lockPackage($packagePurchaseId, $customer, $locked, $count) : null;
            // sessions from a package were paid for when it was bought: this booking itself costs nothing
            $quote = $purchase ? $this->pricing->quotePrice(0, $stable) : $this->pricing->quote($offering, $count, $stable);

            if (! $purchase && ! $quote->isFree() && ! in_array($paymentOption, $stable->bookingSettings()->paymentOptions(), true)) {
                throw new BookingUnavailable(__('stable_bookings.errors.payment_option'));
            }

            $free = $quote->isFree();
            $atStable = ! $free && $paymentOption === self::AT_STABLE;
            $account = ! $free && ! $atStable ? $stable->activePaymentAccount() : null;

            $order = ServiceOrder::create([
                'stable_id' => $stable->getKey(),
                'stable_payment_account_id' => $account?->getKey(),
                'collected_by' => $free ? null : (($atStable || $account) ? 'stable' : 'platform'),
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
            ] + $this->pricing->orderAttributes($quote, $stable));

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

    /** @throws BookingUnavailable */
    public function assertBookable(BookingSlot $slot, int $riders): void
    {
        $offering = $slot->offering;
        $stable = $slot->stable;

        if (! $stable?->acceptsBookings() || ! $offering?->is_active || ! $slot->is_open) {
            throw new BookingUnavailable(__('stable_bookings.errors.not_bookable'));
        }

        if ($slot->bookingClosesAt()->isPast()) {
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
