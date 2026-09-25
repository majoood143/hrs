<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\VideoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideos extends ListRecords
{
    use HasExportActions;

    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
