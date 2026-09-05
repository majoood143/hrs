<?php

namespace App\Filament\Resources\HorseResource\Pages;

use App\Filament\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHorse extends CreateRecord
{
    protected static string $resource = HorseResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');   
    }
}
