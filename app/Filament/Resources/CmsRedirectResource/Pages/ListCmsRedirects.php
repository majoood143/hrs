<?php

namespace App\Filament\Resources\CmsRedirectResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CmsRedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsRedirects extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CmsRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
