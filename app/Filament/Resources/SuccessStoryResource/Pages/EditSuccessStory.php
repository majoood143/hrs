<?php

namespace App\Filament\Resources\SuccessStoryResource\Pages;

use App\Filament\Resources\SuccessStoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSuccessStory extends EditRecord
{
    protected static string $resource = SuccessStoryResource::class;

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
