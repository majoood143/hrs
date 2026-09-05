<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVaccination extends EditRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
        return $this->getResource()::getUrl('index');
    }
}
