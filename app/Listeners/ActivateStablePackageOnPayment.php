<?php

namespace App\Listeners;

use App\Events\ServiceOrderReceived;
use App\Services\Stables\StablePackages;
use Illuminate\Support\Facades\Log;
use Throwable;

/** A lesson package's order was paid: its sessions can be used. Auto-discovered: never Event::listen it. */
class ActivateStablePackageOnPayment
{
    public function __construct(private readonly StablePackages $packages) {}

    public function handle(ServiceOrderReceived $event): void
    {
        if (! $event->order->isStableBooking()) {
            return;
        }

        try {
            if ($purchase = $event->order->packagePurchase()->first()) {
                $this->packages->activate($purchase);
            }
        } catch (Throwable $e) {
            Log::error('Could not activate a paid lesson package', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
