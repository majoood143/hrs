<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\FarrierResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFarriers extends ListRecords
{
    use HasExportActions;

    protected static string $resource = FarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return ListingViewWidgets::forList(static::$resource);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
