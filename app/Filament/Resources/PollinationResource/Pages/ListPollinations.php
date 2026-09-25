<?php

namespace App\Filament\Resources\PollinationResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\PollinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPollinations extends ListRecords
{
    use HasExportActions;

    protected static string $resource = PollinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
