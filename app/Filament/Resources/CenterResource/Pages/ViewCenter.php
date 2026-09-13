<?php

namespace App\Filament\Resources\CenterResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\CenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCenter extends ViewRecord
{
    protected static string $resource = CenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
