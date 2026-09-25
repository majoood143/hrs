<?php

namespace App\Filament\Resources\ClinicServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ClinicServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicServices extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ClinicServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
