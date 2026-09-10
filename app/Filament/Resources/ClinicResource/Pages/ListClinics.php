<?php

namespace App\Filament\Resources\ClinicResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ClinicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinics extends ListRecords
{
    protected static string $resource = ClinicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
