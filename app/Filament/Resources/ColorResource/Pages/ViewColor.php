<?php

namespace App\Filament\Resources\ColorResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\ColorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewColor extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = ColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
