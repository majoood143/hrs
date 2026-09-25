<?php

namespace App\Filament\Resources\CountryResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\CountryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCountries extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
