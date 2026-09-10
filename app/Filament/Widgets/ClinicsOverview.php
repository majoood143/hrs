<?php

namespace App\Filament\Widgets;

use App\Models\Clinic;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClinicsOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Clinic') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Clinics', Clinic::count())
                ->description(Clinic::where('is_active', true)->count() . ' active')
                ->icon('heroicon-o-building-office-2'),
        ];
    }
}
