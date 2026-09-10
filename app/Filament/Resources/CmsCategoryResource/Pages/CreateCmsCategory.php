<?php

namespace App\Filament\Resources\CmsCategoryResource\Pages;

use App\Filament\Resources\CmsCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsCategory extends CreateRecord
{
    protected static string $resource = CmsCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
