<?php

namespace App\Filament\Resources\ClinicResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ClinicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinics extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ClinicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
