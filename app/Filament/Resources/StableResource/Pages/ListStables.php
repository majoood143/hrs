<?php

namespace App\Filament\Resources\StableResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStables extends ListRecords
{
    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
