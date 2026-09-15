<?php

namespace App\Filament\Resources\VideoFolderResource\Pages;

use App\Filament\Resources\VideoFolderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVideoFolder extends EditRecord
{
    protected static string $resource = VideoFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn () => $this->record->videos()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
