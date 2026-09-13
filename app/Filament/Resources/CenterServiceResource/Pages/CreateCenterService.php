<?php

namespace App\Filament\Resources\CenterServiceResource\Pages;

use App\Filament\Resources\CenterServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCenterService extends CreateRecord
{
    protected static string $resource = CenterServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
