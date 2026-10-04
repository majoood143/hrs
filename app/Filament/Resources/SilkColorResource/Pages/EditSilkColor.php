<?php

namespace App\Filament\Resources\SilkColorResource\Pages;

use App\Filament\Resources\SilkColorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSilkColor extends EditRecord
{
    protected static string $resource = SilkColorResource::class;

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
