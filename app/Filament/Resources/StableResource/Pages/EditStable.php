<?php

namespace App\Filament\Resources\StableResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\StableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStable extends EditRecord
{
    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
