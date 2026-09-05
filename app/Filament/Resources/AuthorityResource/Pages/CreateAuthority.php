<?php

namespace App\Filament\Resources\AuthorityResource\Pages;

use App\Filament\Resources\AuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthority extends CreateRecord
{
    protected static string $resource = AuthorityResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');   
    }
}
