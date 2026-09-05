<?php

namespace App\Filament\Resources\PollinationResource\Pages;

use App\Filament\Resources\PollinationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePollination extends CreateRecord
{
    protected static string $resource = PollinationResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');   
    }
}
