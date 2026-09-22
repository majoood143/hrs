<?php

namespace App\Events;

use App\Models\ServiceOrder;
use Illuminate\Foundation\Events\Dispatchable;

/** A reviewer turned the request down. If it was paid, a refund is now due. */
class ServiceOrderRejected
{
    use Dispatchable;

    public function __construct(public readonly ServiceOrder $order) {}
}
