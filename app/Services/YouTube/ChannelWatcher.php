<?php

namespace App\Services\YouTube;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Video;
use App\Models\YouTubeChannel;
use App\Models\YouTubeChannelVideo;
use App\Services\WhatsApp\NewYouTubeVideoAlert;
use App\Support\WhatsAppSettings;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Checks one watched channel: new feed entries go to the inbox (youtube_channel_videos) and the
 * admins get one WhatsApp alert per channel listing them. The first check of a channel only records
 * what is already there (no alert for its 15 latest videos). Shorts are kept as `skipped` on a
 * channel that skips them, and a video already in the library is marked `added` without an alert.
 * Scheduled or live entries are refreshed while they are still in the feed, so the inbox shows
 * how they ended up; they are alerted once only.
 */
class ChannelWatcher
{
    public function __construct(private readonly YouTubeClient $client) {}

    /**
     * @return array{checked: bool, found: int, alerted: int, error: ?string} checked = the feed was read, found = new videos stored, error = the stored error, if any
     */
    public function check(YouTubeChannel $channel): array
    {
        try {
            $entries = $this->client->feed($channel->channel_id);
        } catch (YouTubeException $e) {
            $channel->forceFill(['last_error' => $e->stored(), 'last_error_at' => now()])->save();

            return ['checked' => false, 'found' => 0, 'alerted' => 0, 'error' => $e->stored()];
        }

        $firstCheck = $channel->checked_at === null;
        $known = YouTubeChannelVideo::query()
            ->whereIn('youtube_id', array_column($entries, 'youtube_id'))
            ->get()
            ->keyBy('youtube_id');

        $fresh = array_values(array_filter($entries, fn (array $entry) => ! $known->has($entry['youtube_id'])));
        $stillLive = $known->filter(fn (YouTubeChannelVideo $v) => in_array($v->kind, ['upcoming', 'live'], true));

        $wanted = array_merge(
            array_column(array_filter($fresh, fn (array $entry) => ! ($entry['is_short'] && $channel->skip_shorts)), 'youtube_id'),
            $stillLive->keys()->all(),
        );

        $error = null;
        $details = [];

        try {
            $details = $this->client->details($wanted);
        } catch (YouTubeException $e) {
            // the feed alone still tells us what is new: alert with less detail rather than not at all
            $error = $e->stored();
        }

        $toAlert = new Collection;

        foreach ($fresh as $entry) {
            $video = $this->store($channel, $entry, $details[$entry['youtube_id']] ?? null);

            if ($video !== null && ! $firstCheck && $video->status === YouTubeChannelVideo::STATUS_NEW) {
                $toAlert->push($video);
            }
        }

        foreach ($stillLive as $id => $video) {
            if (isset($details[$id])) {
                $video->forceFill(array_filter($details[$id], fn ($value) => $value !== null))->save();
            }
        }

        $channel->forceFill([
            'checked_at' => now(),
            'last_error' => $error,
            'last_error_at' => $error ? now() : $channel->last_error_at,
        ])->save();

        $alerted = $toAlert->isEmpty() ? 0 : $this->alert($channel, $toAlert);

        return ['checked' => true, 'found' => count($fresh), 'alerted' => $alerted, 'error' => $error];
    }

    /** The new row, or null when another check stored it first. */
    private function store(YouTubeChannel $channel, array $entry, ?array $details): ?YouTubeChannelVideo
    {
        $kind = $entry['is_short'] ? 'short' : ($details['kind'] ?? 'video');
        $skipped = $kind === 'short' && $channel->skip_shorts;
        $libraryId = $skipped ? null : self::libraryVideoId($entry['youtube_id']);

        $video = YouTubeChannelVideo::query()->createOrFirst(['youtube_id' => $entry['youtube_id']], [
            'youtube_channel_id' => $channel->getKey(),
            'title' => mb_substr($entry['title'], 0, 255),
            'description' => $entry['description'],
            'thumbnail_url' => $entry['thumbnail_url'],
            'kind' => $kind,
            'published_at' => $entry['published_at'],
            'scheduled_at' => $details['scheduled_at'] ?? null,
            'duration_seconds' => $details['duration_seconds'] ?? null,
            'embeddable' => $details['embeddable'] ?? null,
            'status' => match (true) {
                $skipped => YouTubeChannelVideo::STATUS_SKIPPED,
                $libraryId !== null => YouTubeChannelVideo::STATUS_ADDED,
                default => YouTubeChannelVideo::STATUS_NEW,
            },
            'library_video_id' => $libraryId,
        ]);

        return $video->wasRecentlyCreated ? $video : null;
    }

    /** A library video that already plays this YouTube id (pasted by hand before), if any. */
    public static function libraryVideoId(string $youtubeId): ?int
    {
        return Video::query()
            ->where('youtube_url', 'like', '%'.$youtubeId.'%')
            ->get(['id', 'youtube_url'])
            ->first(fn (Video $video) => $video->youtube_id === $youtubeId)
            ?->getKey();
    }

    /** @param  Collection<int, YouTubeChannelVideo>  $videos */
    private function alert(YouTubeChannel $channel, Collection $videos): int
    {
        if (! WhatsAppSettings::enabled() || ! WhatsAppSettings::alertEnabled(NewYouTubeVideoAlert::TYPE)) {
            return 0;
        }

        $recipients = WhatsAppSettings::recipients();

        if ($recipients === []) {
            return 0;
        }

        try {
            $message = NewYouTubeVideoAlert::message($channel, $videos);
        } catch (Throwable $e) {
            // the videos are in the inbox either way
            report($e);

            return 0;
        }

        foreach ($recipients as $phone) {
            SendWhatsAppMessage::dispatch($phone, $message, 'new_'.NewYouTubeVideoAlert::TYPE);
        }

        YouTubeChannelVideo::query()->whereKey($videos->modelKeys())->update(['notified_at' => now()]);

        return $videos->count();
    }
}
