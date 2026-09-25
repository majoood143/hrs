<?php

namespace App\Filament\Resources\GenderResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\GenderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGenders extends ListRecords
{
    use HasExportActions;

    protected static string $resource = GenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
