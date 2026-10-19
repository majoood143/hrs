<?php

namespace App\Filament\Resources\YouTubeChannelResource\Pages;

use App\Filament\Resources\YouTubeChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditYouTubeChannel extends EditRecord
{
    protected static string $resource = YouTubeChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            YouTubeChannelResource::checkAction(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
