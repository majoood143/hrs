<?php

namespace App\Filament\Resources\ShopServiceResource\Pages;

use App\Filament\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateShopService extends CreateRecord
{
    protected static string $resource = ShopServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
