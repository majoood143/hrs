<?php

namespace App\Filament\Resources\ClinicResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\ClinicResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClinic extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ClinicResource::class;

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
