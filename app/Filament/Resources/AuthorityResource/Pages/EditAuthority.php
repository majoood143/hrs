<?php

namespace App\Filament\Resources\AuthorityResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\AuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAuthority extends EditRecord
{
    protected static string $resource = AuthorityResource::class;

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
