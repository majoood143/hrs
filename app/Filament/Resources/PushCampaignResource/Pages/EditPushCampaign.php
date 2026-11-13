<?php

namespace App\Filament\Resources\PushCampaignResource\Pages;

use App\Filament\Resources\PushCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPushCampaign extends EditRecord
{
    protected static string $resource = PushCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PushCampaignResource::testAction(fn () => CreatePushCampaign::draft($this->data ?? [])),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
