<?php

namespace App\Filament\Widgets;

use App\Models\Center;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CentersOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Center') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.centers_overview.stat_label'), Center::count())
                ->description(__('admin_widgets.centers_overview.active_suffix', ['count' => Center::where('is_active', true)->count()]))
                ->icon('heroicon-o-building-office'),
        ];
    }
}
