<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Horse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Horse') ?? false;
    }

    protected function getStats(): array
    {
        // One grouped query instead of four counts.
        $byType = CachedWidgetCounts::groupedBy(Horse::class, 'type_id');

        return [
            Stat::make(__('admin_widgets.stats_overview.total_horses'), array_sum($byType)),
            Stat::make(__('admin_widgets.stats_overview.pure_arabian_horses'), $byType[1] ?? 0),
            Stat::make(__('admin_widgets.stats_overview.arabian_horses'), $byType[2] ?? 0),
            Stat::make(__('admin_widgets.stats_overview.thoroughbred_horses'), $byType[3] ?? 0),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('admin_widgets.stats_overview.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin_widgets.stats_overview.description');
    }
}
