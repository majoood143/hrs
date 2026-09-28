<?php

namespace App\Filament\Stable\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return __('stable_panel.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_panel.dashboard.title');
    }
}
