<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ShopResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShops extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
