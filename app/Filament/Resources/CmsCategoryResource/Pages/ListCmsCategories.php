<?php

namespace App\Filament\Resources\CmsCategoryResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CmsCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsCategories extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CmsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
