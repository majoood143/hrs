<?php

namespace App\Services\Stables;

use App\Enums\OrderStatus;
use App\Enums\StableBookingStatus;
use App\Events\StableBookingCancelled;
use App\Events\StableBookingConfirmed;
use App\Jobs\SendStableBookingNotification;
use App\Models\ServiceOrder;
use App\Models\StableBooking;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happens to a booking after it is made: confirmed when its order is received,
 * cancelled (by the customer within the stable's cancellation window, by the stable, or because
 * its checkout expired), and marked attended or no-show by the stable afterwards.
 *
 * A paid booking that is cancelled leaves its order cancelled and paid, which is what makes a
 * refund due (ServiceOrder::refundDue()); refunds are recorded as for every other order.
 */
class StableBookingActions
{
    public function __construct(private readonly OrderPaymentService $payments) {}

    /**
     * The order was received (paid, or free / pay-at-stable at creation). A booking whose unpaid
     * checkout had already expired is revived: the customer paid, so the places are theirs, even
     * if the slot has filled up since (the order's timeline notes it for the stable to sort out).
     */
    public function confirm(StableBooking $booking): bool
    {
        $changed = DB::transaction(function () use ($booking): bool {
            $locked = StableBooking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, [StableBookingStatus::Pending, StableBookingStatus::Cancelled], true)) {
                // already confirmed (free / at the stable are confirmed at creation), or done with
                return false;
            }

            $revived = $locked->status === StableBookingStatus::Cancelled;
            $locked->forceFill([
                'status' => StableBookingStatus::Confirmed,
                'confirmed_at' => now(),
                'cancelled_at' => null,
                'cancellation_source' => null,
            ])->save();

            $slot = $locked->slot()->withBookedRiders()->first();

            if ($revived && $slot && $slot->bookedRiders() > $slot->capacity) {
                $locked->order?->recordEvent('booking_overbooked', null, ['slot' => $slot->getKey()], public: false);
            }

            return true;
        });

        if ($changed) {
            StableBookingConfirmed::dispatch($booking->refresh());
        }

        return $changed;
    }

    /** Whether the customer may still cancel it themselves (the stable decides how late). */
    public function customerMayCancel(StableBooking $booking): bool
    {
        $booking->loadMissing(['slot', 'stable']);

        if (! $booking->isActive() || ! $booking->slot || $booking->slot->hasStarted()) {
            return false;
        }

        // an unpaid checkout can always be dropped
        if ($booking->status === StableBookingStatus::Pending) {
            return true;
        }

        $hours = $booking->stable?->bookingSettings()->cancellationHours();

        return $hours !== null && now()->lessThanOrEqualTo($booking->slot->startsAt()->subHours($hours));
    }

    /** @throws BookingUnavailable */
    public function cancelByCustomer(StableBooking $booking): StableBooking
    {
        if (! $this->customerMayCancel($booking)) {
            throw new BookingUnavailable(__('stable_bookings.errors.cannot_cancel'));
        }

        return $this->cancel($booking, 'customer');
    }

    /** The stable calls a booking off (a sick horse, the weather…); the customer is given the reason. */
    public function cancelByStable(StableBooking $booking, string $reason): StableBooking
    {
        if (! $booking->isActive()) {
            throw new BookingUnavailable(__('stable_bookings.errors.cannot_cancel'));
        }

        return $this->cancel($booking, 'stable', trim($reason));
    }

    /**
     * The order was given up on before it was paid (the expiry sweep, or the customer backing out):
     * the places go back quietly. Called by OrderPaymentService::cancelPending().
     */
    public function orderCancelled(ServiceOrder $order, string $source): void
    {
        StableBooking::query()
            ->where('service_order_id', $order->getKey())
            ->where('status', StableBookingStatus::Pending->value)
            ->update([
                'status' => StableBookingStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancellation_source' => $source === 'system' ? 'expired' : $source,
            ]);
    }

    public function markAttended(StableBooking $booking): StableBooking
    {
        return $this->finish($booking, StableBookingStatus::Completed);
    }

    public function markNoShow(StableBooking $booking): StableBooking
    {
        return $this->finish($booking, StableBookingStatus::NoShow);
    }

    private function cancel(StableBooking $booking, string $source, ?string $reason = null): StableBooking
    {
        $notify = DB::transaction(function () use ($booking, $source, $reason): bool {
            $locked = StableBooking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (! $locked?->isActive()) {
                throw new BookingUnavailable(__('stable_bookings.errors.cannot_cancel'));
            }

            $wasConfirmed = $locked->status === StableBookingStatus::Confirmed;
            $order = $locked->order;

            $locked->forceFill([
                'status' => StableBookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_source' => $source,
                'cancellation_reason' => $reason,
            ])->save();

            if ($order && $order->status === OrderStatus::PendingPayment) {
                $this->payments->cancelPending($order, $source);
            } elseif ($order && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                $order->forceFill([
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_source' => $source,
                ])->save();
                $order->recordEvent('booking_cancelled', $reason, ['source' => $source]);
            }

            return $wasConfirmed;
        });

        $booking->refresh();

        if ($notify) {
            StableBookingCancelled::dispatch($booking);
        }

        return $booking;
    }

    private function finish(StableBooking $booking, StableBookingStatus $status): StableBooking
    {
        $invite = false;

        DB::transaction(function () use ($booking, $status, &$invite): void {
            $locked = StableBooking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, [StableBookingStatus::Confirmed, StableBookingStatus::Completed, StableBookingStatus::NoShow], true)) {
                throw new BookingUnavailable(__('stable_bookings.errors.not_confirmed'));
            }

            $invite = $status === StableBookingStatus::Completed && $locked->status !== StableBookingStatus::Completed;
            $locked->forceFill(['status' => $status, 'attended_at' => $status === StableBookingStatus::Completed ? now() : null])->save();

            // the session took place (or the customer did not come): the order is done with
            $order = $locked->order;

            if ($order && in_array($order->status, [OrderStatus::New, OrderStatus::Processing], true)) {
                $order->forceFill(['status' => OrderStatus::Completed, 'completed_at' => now()])->save();
                $order->recordEvent($status === StableBookingStatus::Completed ? 'booking_attended' : 'booking_no_show', null, public: false);
            }
        });

        // the customer is asked once how it went (the job sends each message only once)
        if ($invite && $booking->order?->customer_phone) {
            SendStableBookingNotification::dispatch($booking->getKey(), 'customer', 'sms', SendStableBookingNotification::REVIEW_INVITE)->afterCommit();
        }

        return $booking->refresh();
    }
}
