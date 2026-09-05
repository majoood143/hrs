<?php

namespace App\Filament\Resources\AttachementResource\Pages;

use App\Filament\Resources\AttachementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttachement extends EditRecord
{
    protected static string $resource = AttachementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
