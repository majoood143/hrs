<?php

namespace App\Filament\Resources\ShopServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopServices extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
