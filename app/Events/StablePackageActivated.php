<?php

namespace App\Events;

use App\Models\StablePackagePurchase;
use Illuminate\Foundation\Events\Dispatchable;

/** A customer's lesson package was paid for and can be used: the customer and the stable are told. */
class StablePackageActivated
{
    use Dispatchable;

    public function __construct(public readonly StablePackagePurchase $purchase) {}
}
