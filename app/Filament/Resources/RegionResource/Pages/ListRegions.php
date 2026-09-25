<?php

namespace App\Filament\Resources\RegionResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegions extends ListRecords
{
    use HasExportActions;

    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
