<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVaccinations extends ListRecords
{
    use HasExportActions;

    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
