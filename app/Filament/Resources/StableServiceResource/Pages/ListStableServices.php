<?php

namespace App\Filament\Resources\StableServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\StableServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStableServices extends ListRecords
{
    use HasExportActions;

    protected static string $resource = StableServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
