<?php

namespace App\Services\WhatsApp;

use App\Filament\Resources\YouTubeChannelVideoResource;
use App\Models\YouTubeChannel;
use App\Models\YouTubeChannelVideo;
use Illuminate\Support\Collection;

/**
 * The admin WhatsApp alert for new videos on a watched YouTube channel: one message per channel
 * and check, each video with its kind, length or start time and link, then the admin inbox where
 * it is added to the video library by hand.
 */
final class NewYouTubeVideoAlert
{
    /** A WhatsAppSettings::ALERTS key; the log type is new_youtube_video. */
    public const TYPE = 'youtube_video';

    /** Videos listed in one message; the rest are counted. */
    public const MAX_LISTED = 5;

    /** @param  Collection<int, YouTubeChannelVideo>  $videos */
    public static function message(YouTubeChannel $channel, Collection $videos): string
    {
        $listed = $videos->take(self::MAX_LISTED);
        $more = $videos->count() - $listed->count();
        $inbox = YouTubeChannelVideoResource::getUrl('index', panel: 'admin');

        return BilingualMessage::make(fn () => implode("\n", array_filter([
            __('whatsapp.youtube.heading', ['channel' => $channel->title]),
            '',
            ...$listed->flatMap(fn (YouTubeChannelVideo $video) => [...self::lines($video), ''])->all(),
            $more > 0 ? __('whatsapp.youtube.more', ['count' => $more]) : null,
            __('whatsapp.youtube.inbox', ['link' => $inbox]),
        ], fn ($line) => $line !== null)));
    }

    /** @return list<string> */
    private static function lines(YouTubeChannelVideo $video): array
    {
        $timezone = (string) config('stable_bookings.timezone', 'Asia/Muscat');

        $kind = match ($video->kind) {
            'live' => __('whatsapp.youtube.live'),
            'upcoming' => $video->scheduled_at
                ? __('whatsapp.youtube.upcoming_at', ['time' => $video->scheduled_at->timezone($timezone)->format('Y-m-d H:i')])
                : __('whatsapp.youtube.upcoming'),
            default => __('whatsapp.youtube.kind', ['kind' => __('admin_youtube.kinds.'.$video->kind)])
                .($video->durationLabel() ? ' · '.$video->durationLabel() : ''),
        };

        return array_values(array_filter([
            '🎬 '.$video->title,
            $kind,
            $video->embeddable === false ? __('whatsapp.youtube.not_embeddable') : null,
            '🔗 https://youtu.be/'.$video->youtube_id,
        ]));
    }
}
