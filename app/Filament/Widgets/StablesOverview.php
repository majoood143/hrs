<?php

namespace App\Filament\Widgets;

use App\Models\Stable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StablesOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Stable') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.stables_overview.stat_label'), Stable::count())
                ->description(__('admin_widgets.stables_overview.active_suffix', ['count' => Stable::where('is_active', true)->count()]))
                ->icon('heroicon-o-home-modern'),
        ];
    }
}
