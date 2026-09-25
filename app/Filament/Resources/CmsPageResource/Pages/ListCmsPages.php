<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CmsPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsPages extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
