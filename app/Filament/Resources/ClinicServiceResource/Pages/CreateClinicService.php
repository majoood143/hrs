<?php

namespace App\Filament\Resources\ClinicServiceResource\Pages;

use App\Filament\Resources\ClinicServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateClinicService extends CreateRecord
{
    protected static string $resource = ClinicServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
