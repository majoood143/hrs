<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Models\YouTubeChannelVideo;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    /** A YouTube inbox entry to fill the form from ("Add to library" in the YouTube inbox). */
    #[Url(as: 'youtube')]
    public ?string $youtubeInboxId = null;

    protected function afterFill(): void
    {
        $inbox = filled($this->youtubeInboxId)
            ? YouTubeChannelVideo::query()->with('channel')->find((int) $this->youtubeInboxId)
            : null;

        if ($inbox !== null) {
            $this->form->fill(array_replace_recursive($this->form->getRawState(), $inbox->libraryFormData()));
        }
    }

    protected function afterCreate(): void
    {
        // the YouTube inbox shows it as in the library, however it was added
        if ($youtubeId = $this->record->youtube_id) {
            YouTubeChannelVideo::query()
                ->where('youtube_id', $youtubeId)
                ->whereIn('status', [YouTubeChannelVideo::STATUS_NEW, YouTubeChannelVideo::STATUS_IGNORED, YouTubeChannelVideo::STATUS_SKIPPED])
                ->update(['status' => YouTubeChannelVideo::STATUS_ADDED, 'library_video_id' => $this->record->getKey()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
