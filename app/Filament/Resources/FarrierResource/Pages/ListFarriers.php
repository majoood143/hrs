<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\FarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFarriers extends ListRecords
{
    use HasExportActions;

    protected static string $resource = FarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
