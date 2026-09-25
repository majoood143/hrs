<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Farrier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FarriersOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Farrier') ?? false;
    }

    protected function getStats(): array
    {
        $counts = CachedWidgetCounts::totalAndMatching(Farrier::class, 'status', 'active');

        return [
            Stat::make(__('admin_widgets.farriers_overview.stat_label'), $counts['total'])
                ->description(__('admin_widgets.farriers_overview.active_suffix', ['count' => $counts['matching']]))
                ->icon('heroicon-o-wrench-screwdriver'),
        ];
    }
}
