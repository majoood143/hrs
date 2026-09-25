<?php

namespace App\Filament\Resources\StableResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStables extends ListRecords
{
    use HasExportActions;

    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
