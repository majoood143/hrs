<?php

namespace App\Listeners;

use App\Events\ServiceOrderReceived;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Support\Facades\Log;
use Throwable;

/** An order is in (paid, or free): if its form has review stages, the order enters review. */
class StartOrderReview
{
    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function handle(ServiceOrderReceived $event): void
    {
        try {
            $this->workflow->startReview($event->order);
        } catch (Throwable $e) {
            Log::error('Could not start the review of an order', ['order' => $event->order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
