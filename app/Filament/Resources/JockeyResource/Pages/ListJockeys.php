<?php

namespace App\Filament\Resources\JockeyResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\JockeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJockeys extends ListRecords
{
    use HasExportActions;

    protected static string $resource = JockeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
