<?php

namespace App\Filament\Resources\ShopServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopService extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
