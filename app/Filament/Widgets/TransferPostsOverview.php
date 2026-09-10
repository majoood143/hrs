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
            Stat::make('Transfer Posts', TransferPost::count())
                ->description(TransferPost::where('status', 'active')->count() . ' active')
                ->icon('heroicon-o-truck'),
        ];
    }
}
