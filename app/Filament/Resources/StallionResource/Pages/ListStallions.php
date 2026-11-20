<?php

namespace App\Filament\Resources\StallionResource\Pages;

use App\Filament\Resources\StallionResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStallions extends ListRecords
{
    protected static string $resource = StallionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
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
