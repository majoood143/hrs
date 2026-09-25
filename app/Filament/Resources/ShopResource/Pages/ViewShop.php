<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\ShopResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShop extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
