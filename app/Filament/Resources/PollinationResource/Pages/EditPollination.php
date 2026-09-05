<?php

namespace App\Filament\Resources\PollinationResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\PollinationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPollination extends EditRecord
{
    protected static string $resource = PollinationResource::class;

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
