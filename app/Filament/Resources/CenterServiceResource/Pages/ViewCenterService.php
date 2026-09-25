<?php

namespace App\Filament\Resources\CenterServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\CenterServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCenterService extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = CenterServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
