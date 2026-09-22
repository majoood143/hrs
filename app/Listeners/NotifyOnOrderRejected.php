<?php

namespace App\Listeners;

use App\Events\ServiceOrderRejected;
use App\Services\Notifications\OrderNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOnOrderRejected
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(ServiceOrderRejected $event): void
    {
        try {
            $this->notifier->notify($event->order, OrderNotifier::REJECTED);
        } catch (Throwable $e) {
            Log::error('Could not queue the rejection notifications', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
