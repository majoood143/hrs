<?php

namespace App\Filament\Resources\PushCampaignResource\Pages;

use App\Filament\Resources\PushCampaignResource;
use App\Models\PushCampaign;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreatePushCampaign extends CreateRecord
{
    protected static string $resource = PushCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PushCampaignResource::testAction(fn () => self::draft($this->data ?? [])),
        ];
    }

    /** The form as it is now, unsaved: what "Send a test" sends. */
    public static function draft(array $data): PushCampaign
    {
        return new PushCampaign(Arr::only($data, ['en_title', 'ar_title', 'en_body', 'ar_body', 'action_type', 'action_screen', 'action_url',
            'priority', 'ttl_hours', 'sound', 'channel']));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
