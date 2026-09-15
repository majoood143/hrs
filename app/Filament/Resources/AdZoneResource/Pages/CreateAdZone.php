<?php

namespace App\Filament\Resources\AdZoneResource\Pages;

use App\Filament\Resources\AdZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdZone extends CreateRecord
{
    protected static string $resource = AdZoneResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
