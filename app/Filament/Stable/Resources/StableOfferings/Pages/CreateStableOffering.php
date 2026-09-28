<?php

namespace App\Filament\Stable\Resources\StableOfferings\Pages;

use App\Filament\Stable\Resources\StableOfferings\StableOfferingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStableOffering extends CreateRecord
{
    protected static string $resource = StableOfferingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
