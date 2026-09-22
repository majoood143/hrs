<?php

namespace App\Listeners;

use App\Events\ServiceOrderRefunded;
use App\Services\Notifications\OrderNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOnOrderRefunded
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(ServiceOrderRefunded $event): void
    {
        try {
            // every refund is its own news (there can be several), so it is never treated as already sent
            $this->notifier->notify($event->order, OrderNotifier::REFUNDED, force: true);
        } catch (Throwable $e) {
            Log::error('Could not queue the refund notifications', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
