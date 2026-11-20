<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use App\Filament\Resources\TransferPostResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTransferPost extends EditRecord
{
    protected static string $resource = TransferPostResource::class;

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
