<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use App\Filament\Resources\FarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFarrier extends CreateRecord
{
    protected static string $resource = FarrierResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
