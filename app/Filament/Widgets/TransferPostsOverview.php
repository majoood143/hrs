<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\TransferPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransferPostsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 1;

    // No 5s auto-refresh; the counts are cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:TransferPost') ?? false;
    }

    protected function getStats(): array
    {
        $counts = CachedWidgetCounts::totalAndMatching(TransferPost::class, 'status', 'active');

        return [
            Stat::make(__('admin_widgets.transfer_posts_overview.stat_label'), $counts['total'])
                ->description(__('admin_widgets.transfer_posts_overview.active_suffix', ['count' => $counts['matching']]))
                ->icon('heroicon-o-truck'),
        ];
    }
}
