<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Stable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StablesOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Stable') ?? false;
    }

    protected function getStats(): array
    {
        $counts = CachedWidgetCounts::totalAndMatching(Stable::class, 'is_active', true);

        return [
            Stat::make(__('admin_widgets.stables_overview.stat_label'), $counts['total'])
                ->description(__('admin_widgets.stables_overview.active_suffix', ['count' => $counts['matching']]))
                ->icon('heroicon-o-home-modern'),
        ];
    }
}
