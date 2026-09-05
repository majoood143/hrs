<?php

namespace App\Filament\Resources\PollinationResource\Pages;

use App\Filament\Resources\PollinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPollinations extends ListRecords
{
    protected static string $resource = PollinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
