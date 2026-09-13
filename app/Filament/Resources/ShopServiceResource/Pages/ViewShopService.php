<?php

namespace App\Filament\Resources\ShopServiceResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopService extends ViewRecord
{
    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
