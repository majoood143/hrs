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
            Stat::make(__('admin_widgets.clinics_overview.stat_label'), Clinic::count())
                ->description(__('admin_widgets.clinics_overview.active_suffix', ['count' => Clinic::where('is_active', true)->count()]))
                ->icon('heroicon-o-building-office-2'),
        ];
    }
}
