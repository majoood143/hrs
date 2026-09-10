<?php

namespace App\Filament\Resources\StableServiceResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\StableServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStableService extends EditRecord
{
    protected static string $resource = StableServiceResource::class;

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
