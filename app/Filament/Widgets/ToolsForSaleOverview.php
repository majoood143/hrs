<?php

namespace App\Filament\Widgets;

use App\Models\ToolSalePost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ToolsForSaleOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:ToolSalePost') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Tools for Sale', ToolSalePost::count())
                ->description(ToolSalePost::where('status', 'active')->count() . ' active')
                ->icon('heroicon-o-wrench'),
        ];
    }
}
