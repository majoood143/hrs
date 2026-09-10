<?php

namespace App\Filament\Resources\ClinicResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\ClinicResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClinic extends ViewRecord
{
    protected static string $resource = ClinicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
