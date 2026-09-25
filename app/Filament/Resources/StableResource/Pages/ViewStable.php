<?php

namespace App\Filament\Resources\StableResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStable extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
