<?php

namespace App\Filament\Widgets;

use App\Models\Stable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StablesOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Stable') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Stables', Stable::count())
                ->description(Stable::where('is_active', true)->count() . ' active')
                ->icon('heroicon-o-home-modern'),
        ];
    }
}
