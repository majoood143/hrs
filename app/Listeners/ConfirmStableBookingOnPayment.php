<?php

namespace App\Listeners;

use App\Events\ServiceOrderReceived;
use App\Services\Stables\StableBookingActions;
use Illuminate\Support\Facades\Log;
use Throwable;

/** A stable booking's order was paid: the booking is confirmed. Auto-discovered: never Event::listen it. */
class ConfirmStableBookingOnPayment
{
    public function __construct(private readonly StableBookingActions $actions) {}

    public function handle(ServiceOrderReceived $event): void
    {
        $order = $event->order;

        if (! $order->isStableBooking()) {
            return;
        }

        try {
            if ($booking = $order->stableBooking()->first()) {
                $this->actions->confirm($booking);
            }
        } catch (Throwable $e) {
            // the payment is recorded either way; the stable can still see the paid order
            Log::error('Could not confirm a paid stable booking', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
