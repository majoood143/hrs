<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\CityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
