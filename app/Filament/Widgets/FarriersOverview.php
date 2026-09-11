<?php

namespace App\Filament\Widgets;

use App\Models\Farrier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FarriersOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Farrier') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.farriers_overview.stat_label'), Farrier::count())
                ->description(__('admin_widgets.farriers_overview.active_suffix', ['count' => Farrier::where('status', 'active')->count()]))
                ->icon('heroicon-o-wrench-screwdriver'),
        ];
    }
}
