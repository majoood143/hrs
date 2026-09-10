<?php

namespace App\Filament\Resources\CmsTagResource\Pages;

use App\Filament\Resources\CmsTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsTags extends ListRecords
{
    protected static string $resource = CmsTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
