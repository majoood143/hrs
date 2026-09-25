<?php

namespace App\Filament\Resources\EventCategoryResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\EventCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventCategories extends ListRecords
{
    use HasExportActions;

    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
