<?php

namespace App\Filament\Resources\HorseResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\EditAction;
use App\Filament\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHorse extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
