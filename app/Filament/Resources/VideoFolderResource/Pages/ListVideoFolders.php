<?php

namespace App\Filament\Resources\VideoFolderResource\Pages;

use App\Filament\Resources\VideoFolderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideoFolders extends ListRecords
{
    protected static string $resource = VideoFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
