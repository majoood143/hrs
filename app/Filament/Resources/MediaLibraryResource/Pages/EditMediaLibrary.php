<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\MediaLibraryResource;
use Filament\Resources\Pages\EditRecord;

class EditMediaLibrary extends EditRecord
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
