<?php

namespace App\Filament\Resources\ColorResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\ColorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditColor extends EditRecord
{
    protected static string $resource = ColorResource::class;

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
