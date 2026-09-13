<?php

namespace App\Filament\Widgets;

use App\Models\Shop;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopsOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Shop') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.shops_overview.stat_label'), Shop::count())
                ->description(__('admin_widgets.shops_overview.active_suffix', ['count' => Shop::where('is_active', true)->count()]))
                ->icon('heroicon-o-shopping-bag'),
        ];
    }
}
