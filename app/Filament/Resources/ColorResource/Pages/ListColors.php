<?php

namespace App\Filament\Resources\ColorResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ColorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListColors extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
