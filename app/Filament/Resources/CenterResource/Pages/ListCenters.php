<?php

namespace App\Filament\Resources\CenterResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\CenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCenters extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
