<?php

namespace App\Filament\Resources\HorseResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHorse extends EditRecord
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
        return $this->getResource()::getUrl('index');
    }
}
