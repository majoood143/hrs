<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
