<?php

namespace App\Filament\Resources\CmsRedirectResource\Pages;

use App\Filament\Resources\CmsRedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsRedirect extends CreateRecord
{
    protected static string $resource = CmsRedirectResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
