<?php

namespace App\Filament\Resources\AdZoneResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\AdZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdZones extends ListRecords
{
    use HasExportActions;

    protected static string $resource = AdZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
