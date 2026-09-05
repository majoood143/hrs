<?php

namespace App\Filament\Resources\HorseResource\Pages;

use App\Filament\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHorse extends ViewRecord
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
