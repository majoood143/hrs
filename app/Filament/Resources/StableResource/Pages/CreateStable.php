<?php

namespace App\Filament\Resources\StableResource\Pages;

use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStable extends CreateRecord
{
    protected static string $resource = StableResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
