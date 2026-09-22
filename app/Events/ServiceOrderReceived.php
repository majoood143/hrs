<?php

namespace App\Events;

use App\Models\ServiceOrder;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order is now confirmed: it was just paid, or it is a free one that was just placed.
 * The moment to tell the customer its order number and to start the review.
 */
class ServiceOrderReceived
{
    use Dispatchable;

    public function __construct(public readonly ServiceOrder $order) {}
}
