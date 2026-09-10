<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\FarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFarrier extends EditRecord
{
    protected static string $resource = FarrierResource::class;

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
