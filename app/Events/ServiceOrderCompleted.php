<?php

namespace App\Events;

use App\Models\ServiceOrder;
use Illuminate\Foundation\Events\Dispatchable;

/** The request has been fulfilled: the moment to tell the customer it is ready. */
class ServiceOrderCompleted
{
    use Dispatchable;

    public function __construct(public readonly ServiceOrder $order) {}
}
