<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\EventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    use HasExportActions;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
