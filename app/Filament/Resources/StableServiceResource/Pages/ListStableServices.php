<?php

namespace App\Filament\Resources\StableServiceResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\StableServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStableServices extends ListRecords
{
    protected static string $resource = StableServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
