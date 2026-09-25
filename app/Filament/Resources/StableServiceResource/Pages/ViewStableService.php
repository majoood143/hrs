<?php

namespace App\Filament\Resources\StableServiceResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\StableServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStableService extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = StableServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
