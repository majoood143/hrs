<?php

namespace App\Filament\Insights;

use App\Models\Stable;
use App\Services\Reports\StableInsights;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * The insights widgets serve both panels: in /stable they show the stable open there; in /admin
 * the stable picked in the page's filters, or every stable. The period comes from the page filters.
 */
trait ResolvesInsights
{
    use InteractsWithPageFilters;

    private ?StableInsights $insightsCache = null;

    protected function insights(): StableInsights
    {
        return $this->insightsCache ??= StableInsights::forPeriod(
            $this->scopeStable(),
            (string) ($this->pageFilters['period'] ?? 'this_month'),
            $this->pageFilters['date_from'] ?? null,
            $this->pageFilters['date_to'] ?? null,
        );
    }

    protected function scopeStable(): ?Stable
    {
        if (Filament::getCurrentPanel()?->getId() === 'stable') {
            $tenant = Filament::getTenant();

            return $tenant instanceof Stable ? $tenant : null;
        }

        $id = $this->pageFilters['stable_id'] ?? null;

        return $id ? Stable::query()->find($id) : null;
    }

    /** Owners see "your share"; admins looking at every stable see "our earnings". */
    protected function forOwner(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'stable';
    }
}
