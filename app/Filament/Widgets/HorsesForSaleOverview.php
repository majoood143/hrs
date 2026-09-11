<?php

namespace App\Filament\Widgets;

use App\Models\HorseSalePost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HorsesForSaleOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:HorseSalePost') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.horses_for_sale_overview.stat_label'), HorseSalePost::count())
                ->description(__('admin_widgets.horses_for_sale_overview.active_suffix', ['count' => HorseSalePost::where('status', 'active')->count()]))
                ->icon('heroicon-o-tag'),
        ];
    }
}
