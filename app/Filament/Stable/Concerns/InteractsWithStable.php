<?php

namespace App\Filament\Stable\Concerns;

use App\Models\Stable;
use Filament\Facades\Filament;

/** The stable the owner is working in (the panel's current tenant). */
trait InteractsWithStable
{
    protected static function stable(): Stable
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return $stable;
    }

    protected static function staffEnabled(): bool
    {
        return Filament::getTenant() instanceof Stable && static::stable()->bookingSettings()->staffEnabled();
    }
}
