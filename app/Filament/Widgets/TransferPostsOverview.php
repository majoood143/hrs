<?php

namespace App\Filament\Widgets;

use App\Models\TransferPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransferPostsOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:TransferPost') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin_widgets.transfer_posts_overview.stat_label'), TransferPost::count())
                ->description(__('admin_widgets.transfer_posts_overview.active_suffix', ['count' => TransferPost::where('status', 'active')->count()]))
                ->icon('heroicon-o-truck'),
        ];
    }
}
