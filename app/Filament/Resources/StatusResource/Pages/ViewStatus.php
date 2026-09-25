<?php

namespace App\Filament\Resources\StatusResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\StatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStatus extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
