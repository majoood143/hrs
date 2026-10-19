<?php

namespace App\Filament\Resources\YouTubeChannelResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\YouTubeChannelResource;
use App\Models\YouTubeChannel;
use App\Services\YouTube\ChannelWatcher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListYouTubeChannels extends ListRecords
{
    use HasExportActions;

    protected static string $resource = YouTubeChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check_all')
                ->label(__('admin_youtube.actions.check_all'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => YouTubeChannel::query()->active()->exists())
                ->action(function (ChannelWatcher $watcher) {
                    $found = YouTubeChannel::query()->active()->get()
                        ->sum(fn (YouTubeChannel $channel) => $watcher->check($channel)['found']);

                    Notification::make()
                        ->title(__('admin_youtube.notifications.checked_all'))
                        ->body(__('admin_youtube.notifications.checked_all_body', ['found' => $found]))
                        ->success()
                        ->send();
                }),
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
