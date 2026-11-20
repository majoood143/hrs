<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\ShopResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\EditAction;
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

    protected function getFooterWidgets(): array
    {
        return ListingViewWidgets::forRecord(static::$resource);
    }
}
