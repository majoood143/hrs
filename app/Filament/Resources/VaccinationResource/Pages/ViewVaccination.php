<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVaccination extends ViewRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
