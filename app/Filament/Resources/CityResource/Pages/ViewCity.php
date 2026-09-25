<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\CityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCity extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
