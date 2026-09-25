<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\MediaLibraryResource;
use Filament\Resources\Pages\ListRecords;

class ListMediaLibraries extends ListRecords
{
    use HasExportActions;

    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
