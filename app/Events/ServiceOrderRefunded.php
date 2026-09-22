<?php

namespace App\Events;

use App\Models\OrderRefund;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Events\Dispatchable;

/** A refund was recorded: the customer is told how much and that the service fee is kept. */
class ServiceOrderRefunded
{
    use Dispatchable;

    public function __construct(public readonly ServiceOrder $order, public readonly OrderRefund $refund) {}
}
