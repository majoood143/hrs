<?php

namespace App\Filament\Resources\AdZoneResource\Pages;

use App\Filament\Resources\AdZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdZone extends EditRecord
{
    protected static string $resource = AdZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn () => $this->record->ads()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
