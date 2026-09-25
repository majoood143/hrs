<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Clinic;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClinicsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Clinic') ?? false;
    }

    protected function getStats(): array
    {
        $counts = CachedWidgetCounts::totalAndMatching(Clinic::class, 'is_active', true);

        return [
            Stat::make(__('admin_widgets.clinics_overview.stat_label'), $counts['total'])
                ->description(__('admin_widgets.clinics_overview.active_suffix', ['count' => $counts['matching']]))
                ->icon('heroicon-o-building-office-2'),
        ];
    }
}
