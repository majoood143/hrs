<?php

namespace App\Events;

use App\Models\Stable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** An owner registered a stable from the /stable panel; it waits for an admin. */
class StableRegistered
{
    use Dispatchable;

    public function __construct(public readonly Stable $stable, public readonly User $owner) {}
}
