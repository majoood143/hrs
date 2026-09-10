<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\HorseSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHorseSalePost extends EditRecord
{
    protected static string $resource = HorseSalePostResource::class;

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
