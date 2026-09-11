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
            Stat::make(__('admin_widgets.stats_overview.total_horses'), Horse::count()),
            Stat::make(__('admin_widgets.stats_overview.pure_arabian_horses'), Horse::make()->where('type_id', 1)->count()),
            Stat::make(__('admin_widgets.stats_overview.arabian_horses'), Horse::make()->where('type_id', 2)->count()),
            Stat::make(__('admin_widgets.stats_overview.thoroughbred_horses'), Horse::make()->where('type_id', 3)->count()),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('admin_widgets.stats_overview.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin_widgets.stats_overview.description');
    }
}
