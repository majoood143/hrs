<?php

namespace App\Filament\Resources\YouTubeChannelResource\Pages;

use App\Filament\Resources\YouTubeChannelResource;
use App\Models\YouTubeChannel;
use App\Services\YouTube\ChannelWatcher;
use App\Services\YouTube\YouTubeClient;
use App\Services\YouTube\YouTubeException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * The admin pastes a channel link; it is turned into the channel (id, title, picture) here, then
 * checked once right away: that first check fills the inbox with the channel's recent videos
 * without alerting anyone, so only videos posted from now on are sent by WhatsApp.
 */
class CreateYouTubeChannel extends CreateRecord
{
    protected static string $resource = YouTubeChannelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            $channel = app(YouTubeClient::class)->resolveChannel((string) $data['url']);
        } catch (YouTubeException $e) {
            throw ValidationException::withMessages(['data.url' => YouTubeException::label($e->stored())]);
        }

        if (YouTubeChannel::query()->where('channel_id', $channel['channel_id'])->exists()) {
            throw ValidationException::withMessages(['data.url' => __('admin_youtube.validation.duplicate')]);
        }

        unset($data['url']);

        return [...$data, ...$channel];
    }

    protected function afterCreate(): void
    {
        /** @var YouTubeChannel $channel */
        $channel = $this->record;
        $result = app(ChannelWatcher::class)->check($channel);

        Notification::make()
            ->title(__('admin_youtube.notifications.added', ['channel' => $channel->title]))
            ->body(collect([
                $result['checked'] ? __('admin_youtube.notifications.added_body', ['count' => $result['found']]) : null,
                YouTubeException::label($result['error']),
            ])->filter()->implode(' '))
            ->{$result['error'] ? 'warning' : 'success'}()
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
