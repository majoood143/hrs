<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\ToolSalePost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ToolsForSaleOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:ToolSalePost') ?? false;
    }

    protected function getStats(): array
    {
        $counts = CachedWidgetCounts::totalAndMatching(ToolSalePost::class, 'status', 'active');

        return [
            Stat::make(__('admin_widgets.tools_for_sale_overview.stat_label'), $counts['total'])
                ->description(__('admin_widgets.tools_for_sale_overview.active_suffix', ['count' => $counts['matching']]))
                ->icon('heroicon-o-wrench'),
        ];
    }
}
