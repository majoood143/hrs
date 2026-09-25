<?php

namespace App\Filament\Resources\ClinicServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\ClinicServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClinicService extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ClinicServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
