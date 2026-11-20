<?php

namespace App\Filament\Resources\CenterResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CenterResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCenter extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = CenterResource::class;

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
