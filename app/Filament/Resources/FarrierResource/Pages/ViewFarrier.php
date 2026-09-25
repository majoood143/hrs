<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\FarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFarrier extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = FarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
