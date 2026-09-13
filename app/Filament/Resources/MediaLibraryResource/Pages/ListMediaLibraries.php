<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MediaLibraryResource;
use Filament\Resources\Pages\ListRecords;

class ListMediaLibraries extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
