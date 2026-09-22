<?php

namespace App\Listeners;

use App\Events\ServiceOrderReceived;
use App\Jobs\SendReviewerNotification;
use App\Services\Notifications\OrderNotifier;
use App\Support\FormOrderSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

/** An order is in (paid, or free): tell the customer, and the reviewers of a form whose email waited for payment. */
class NotifyOnOrderReceived
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function handle(ServiceOrderReceived $event): void
    {
        $order = $event->order;

        // Nothing here may break the payment or order that triggered it.
        try {
            $this->notifier->notify($order, OrderNotifier::RECEIVED);

            if ($order->isPaid() && $order->form && FormOrderSettings::for($order->form)->heldUntilPaid()) {
                SendReviewerNotification::dispatch($order->getKey());
            }
        } catch (Throwable $e) {
            Log::error('Could not queue the order notifications', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
