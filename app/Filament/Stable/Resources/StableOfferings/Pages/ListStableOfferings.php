<?php

namespace App\Filament\Stable\Resources\StableOfferings\Pages;

use App\Filament\Stable\Resources\StableOfferings\StableOfferingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStableOfferings extends ListRecords
{
    protected static string $resource = StableOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
