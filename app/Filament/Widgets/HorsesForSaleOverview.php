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
            Stat::make('Horses for Sale', HorseSalePost::count())
                ->description(HorseSalePost::where('status', 'active')->count() . ' active')
                ->icon('heroicon-o-tag'),
        ];
    }
}
