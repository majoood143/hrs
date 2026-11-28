<?php

namespace App\Listeners;

use App\Events\ServiceOrderReceived;
use App\Services\Auctions\Registration;
use Illuminate\Support\Facades\Log;
use Throwable;

/** An auction deposit was paid: the registration may be approved. Auto-discovered: never Event::listen it. */
class ApproveAuctionBidderOnDeposit
{
    public function __construct(private readonly Registration $registration) {}

    public function handle(ServiceOrderReceived $event): void
    {
        if (! $event->order->auction_bidder_id) {
            return;
        }

        try {
            $this->registration->depositPaid($event->order);
        } catch (Throwable $e) {
            Log::error('Could not approve a paid auction deposit', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
