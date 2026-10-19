<?php

namespace App\Filament\Resources\YouTubeChannelVideoResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\YouTubeChannelResource;
use App\Filament\Resources\YouTubeChannelVideoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListYouTubeChannelVideos extends ListRecords
{
    use HasExportActions;

    protected static string $resource = YouTubeChannelVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('channels')
                ->label(__('admin_youtube.channels.plural'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn () => YouTubeChannelResource::canViewAny())
                ->url(YouTubeChannelResource::getUrl('index')),
        ];
    }
}
