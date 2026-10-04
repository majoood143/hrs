<?php

namespace App\Filament\Resources\SilkColorResource\Pages;

use App\Filament\Resources\SilkColorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSilkColor extends CreateRecord
{
    protected static string $resource = SilkColorResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
