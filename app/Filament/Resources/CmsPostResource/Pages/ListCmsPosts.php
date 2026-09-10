<?php

namespace App\Filament\Resources\CmsPostResource\Pages;

use App\Filament\Resources\CmsPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsPosts extends ListRecords
{
    protected static string $resource = CmsPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
