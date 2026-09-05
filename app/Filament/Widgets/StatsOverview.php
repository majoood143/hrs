<?php

namespace App\Filament\Widgets;

use App\Models\Country;
use App\Models\Horse;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Horses', Horse::count()),
            Stat::make('Pure Arabian Horses', Horse::make()->where('type_id', 1)->count()),
            Stat::make('Arabian Horses', Horse::make()->where('type_id', 2)->count()),
            Stat::make('Thoroughbred Horses', Horse::make()->where('type_id', 3)->count()),
        ];
    }

    protected function getHeading(): ?string
    {
        return 'Analytics';
    }

    protected function getDescription(): ?string
    {
        return 'An overview of some analytics.';
    }
}
