<?php

namespace App\Filament\Resources\CenterServiceResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CenterServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCenterServices extends ListRecords
{
    protected static string $resource = CenterServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
