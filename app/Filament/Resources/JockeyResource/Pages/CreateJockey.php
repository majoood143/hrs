<?php

namespace App\Filament\Resources\JockeyResource\Pages;

use App\Filament\Resources\JockeyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJockey extends CreateRecord
{
    protected static string $resource = JockeyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
