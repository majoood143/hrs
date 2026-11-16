<?php

namespace App\Filament\Resources\StallionResource\Pages;

use App\Filament\Resources\StallionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStallion extends CreateRecord
{
    protected static string $resource = StallionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
