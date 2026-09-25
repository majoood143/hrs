<?php

namespace App\Filament\Resources\PollinationResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\PollinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPollination extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = PollinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
