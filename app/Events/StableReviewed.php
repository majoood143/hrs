<?php

namespace App\Events;

use App\Models\Stable;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin approved, rejected or suspended a stable; its owners are told. */
class StableReviewed
{
    use Dispatchable;

    public function __construct(public readonly Stable $stable) {}
}
