<?php

namespace App\Filament\Resources\ShopServiceResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopService extends EditRecord
{
    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
