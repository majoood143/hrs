<?php

namespace App\Listeners;

use App\Events\ServiceOrderCompleted;
use App\Services\Notifications\OrderNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOnOrderCompleted
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(ServiceOrderCompleted $event): void
    {
        try {
            $this->notifier->notify($event->order, OrderNotifier::COMPLETED);
        } catch (Throwable $e) {
            Log::error('Could not queue the completion notifications', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
