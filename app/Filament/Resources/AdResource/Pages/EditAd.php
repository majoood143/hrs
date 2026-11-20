<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Resources\AdResource;
use App\Filament\Resources\AdResource\Widgets\AdActivityChart;
use App\Filament\Resources\AdResource\Widgets\AdSources;
use App\Filament\Resources\AdResource\Widgets\AdStatsOverview;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAd extends EditRecord
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** This promotion's own figures above its form. */
    protected function getHeaderWidgets(): array
    {
        return [AdStatsOverview::class, AdActivityChart::class, AdSources::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
