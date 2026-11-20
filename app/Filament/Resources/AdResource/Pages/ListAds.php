<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\AdResource;
use App\Filament\Resources\AdResource\Widgets\AdActivityChart;
use App\Filament\Resources\AdResource\Widgets\AdSources;
use App\Filament\Resources\AdResource\Widgets\AdStatsOverview;
use App\Filament\Resources\AdResource\Widgets\TopAds;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAds extends ListRecords
{
    use HasExportActions;

    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [AdStatsOverview::class, AdActivityChart::class, AdSources::class, TopAds::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
