<?php

namespace App\Filament\Resources\StableServiceResource\Pages;

use App\Filament\Resources\StableServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStableService extends CreateRecord
{
    protected static string $resource = StableServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
