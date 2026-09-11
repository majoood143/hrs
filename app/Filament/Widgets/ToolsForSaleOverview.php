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
            Stat::make(__('admin_widgets.tools_for_sale_overview.stat_label'), ToolSalePost::count())
                ->description(__('admin_widgets.tools_for_sale_overview.active_suffix', ['count' => ToolSalePost::where('status', 'active')->count()]))
                ->icon('heroicon-o-wrench'),
        ];
    }
}
