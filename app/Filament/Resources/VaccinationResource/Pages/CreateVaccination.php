<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVaccination extends CreateRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');   
    }
}
