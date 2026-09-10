<?php

namespace App\Filament\Resources\StableResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStable extends ViewRecord
{
    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
