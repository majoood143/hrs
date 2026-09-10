<?php

namespace App\Filament\Widgets;

use App\Models\Farrier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FarriersOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Farrier') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Farriers', Farrier::count())
                ->description(Farrier::where('status', 'active')->count() . ' active')
                ->icon('heroicon-o-wrench-screwdriver'),
        ];
    }
}
