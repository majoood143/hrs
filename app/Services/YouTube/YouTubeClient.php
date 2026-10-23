<?php

namespace App\Services\YouTube;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * The only thing that talks to YouTube. A channel's public feed (free, no key; its last 15 uploads,
 * Shorts linked as /shorts/…) says what is new; the Data API (optional key, config
 * services.youtube.key; 1 quota unit per call of up to 50 videos) adds live/upcoming status,
 * duration and whether the video may be embedded. When the feed fails and there is a key, the
 * channel's uploads playlist stands in for it (YouTube's feeds answer 404 for every channel at times).
 */
class YouTubeClient
{
    private const FEED_URL = 'https://www.youtube.com/feeds/videos.xml';

    private const API_URL = 'https://www.googleapis.com/youtube/v3/';

    private const TIMEOUT = 15;

    public function hasKey(): bool
    {
        return filled(config('services.youtube.key'));
    }

    /**
     * What an admin pasted (a channel link, an @handle or a UC… id) → the channel.
     *
     * @return array{channel_id: string, handle: ?string, title: string, thumbnail_url: ?string}
     */
    public function resolveChannel(string $input): array
    {
        $parsed = self::parseChannelInput($input);

        if ($parsed === null) {
            throw new YouTubeException('not_found');
        }

        if ($this->hasKey()) {
            $item = $this->api('channels', ['part' => 'snippet', ...(isset($parsed['id']) ? ['id' => $parsed['id']] : ['forHandle' => $parsed['handle']])])['items'][0] ?? null;

            if ($item === null) {
                throw new YouTubeException('not_found');
            }

            return [
                'channel_id' => $item['id'],
                'handle' => $item['snippet']['customUrl'] ?? $parsed['handle'] ?? null,
                'title' => $item['snippet']['title'] ?? $item['id'],
                'thumbnail_url' => $item['snippet']['thumbnails']['default']['url'] ?? null,
            ];
        }

        $channelId = $parsed['id'] ?? $this->channelIdFromPage($parsed['url']);
        $feed = $this->feedXml($channelId);

        return [
            'channel_id' => $channelId,
            'handle' => $parsed['handle'] ?? null,
            'title' => trim((string) $feed->title) ?: $channelId,
            'thumbnail_url' => null,
        ];
    }

    /**
     * The channel's latest uploads, newest first.
     *
     * @return list<array{youtube_id: string, title: string, description: ?string, thumbnail_url: ?string, published_at: ?Carbon, is_short: bool}>
     */
    public function feed(string $channelId): array
    {
        try {
            $feed = $this->feedXml($channelId);
        } catch (YouTubeException $e) {
            if (! $this->hasKey()) {
                throw $e;
            }

            return $this->uploads($channelId);
        }
        $entries = [];

        foreach ($feed->entry as $entry) {
            $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $media = $entry->children('http://search.yahoo.com/mrss/')->group;
            $id = trim((string) $yt->videoId);

            if ($id === '') {
                continue;
            }

            $link = '';
            foreach ($entry->link as $l) {
                if ((string) $l['rel'] === 'alternate') {
                    $link = (string) $l['href'];
                }
            }

            $description = $media ? trim((string) $media->children('http://search.yahoo.com/mrss/')->description) : '';
            $thumbnail = $media ? (string) ($media->children('http://search.yahoo.com/mrss/')->thumbnail?->attributes()['url'] ?? '') : '';
            $published = trim((string) $entry->published);

            $entries[] = [
                'youtube_id' => $id,
                'title' => trim((string) $entry->title) ?: $id,
                'description' => $description === '' ? null : $description,
                'thumbnail_url' => $thumbnail === '' ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg" : $thumbnail,
                'published_at' => $published === '' ? null : Carbon::parse($published),
                'is_short' => str_contains($link, '/shorts/'),
            ];
        }

        return $entries;
    }

    /**
     * The feed's entries from the Data API: the channel's uploads playlist (UU…) and its Shorts
     * playlist (UUSH…, absent when the channel has none), 1 quota unit each.
     *
     * @return list<array{youtube_id: string, title: string, description: ?string, thumbnail_url: ?string, published_at: ?Carbon, is_short: bool}>
     */
    private function uploads(string $channelId): array
    {
        $suffix = substr($channelId, 2);
        $items = $this->playlistItems('UU'.$suffix) ?? throw new YouTubeException('not_found');
        $shorts = array_column($this->playlistItems('UUSH'.$suffix) ?? [], 'contentDetails');
        $shortIds = array_flip(array_column($shorts, 'videoId'));
        $entries = [];

        foreach ($items as $item) {
            $id = (string) ($item['contentDetails']['videoId'] ?? '');

            // private and deleted videos stay in the playlist; the feed leaves them out
            if ($id === '' || ($item['status']['privacyStatus'] ?? 'public') !== 'public') {
                continue;
            }

            $snippet = $item['snippet'] ?? [];
            $description = trim((string) ($snippet['description'] ?? ''));
            $published = $item['contentDetails']['videoPublishedAt'] ?? $snippet['publishedAt'] ?? null;

            $entries[] = [
                'youtube_id' => $id,
                'title' => trim((string) ($snippet['title'] ?? '')) ?: $id,
                'description' => $description === '' ? null : $description,
                'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
                'published_at' => $published ? Carbon::parse($published) : null,
                'is_short' => isset($shortIds[$id]),
            ];
        }

        return $entries;
    }

    /** A playlist's latest 15 items, or null when YouTube has no such playlist. */
    private function playlistItems(string $playlistId): ?array
    {
        try {
            return $this->api('playlistItems', ['part' => 'snippet,contentDetails,status', 'playlistId' => $playlistId, 'maxResults' => '15'])['items'] ?? [];
        } catch (YouTubeException $e) {
            if ($e->detail === 'playlistNotFound') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Live status, duration and embeddability per video id ([] without an API key).
     *
     * @param  list<string>  $ids
     * @return array<string, array{kind: string, scheduled_at: ?Carbon, duration_seconds: ?int, embeddable: ?bool}>
     */
    public function details(array $ids): array
    {
        if (! $this->hasKey() || $ids === []) {
            return [];
        }

        $details = [];

        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $items = $this->api('videos', [
                'part' => 'snippet,contentDetails,liveStreamingDetails,status',
                'id' => implode(',', $chunk),
            ])['items'] ?? [];

            foreach ($items as $item) {
                $live = $item['liveStreamingDetails'] ?? null;
                $broadcast = $item['snippet']['liveBroadcastContent'] ?? 'none';
                $scheduled = $live['scheduledStartTime'] ?? null;

                $details[$item['id']] = [
                    'kind' => match (true) {
                        $broadcast === 'live' => 'live',
                        $broadcast === 'upcoming' => 'upcoming',
                        $live !== null => 'stream',
                        default => 'video',
                    },
                    'scheduled_at' => $broadcast === 'upcoming' && $scheduled ? Carbon::parse($scheduled) : null,
                    'duration_seconds' => self::seconds($item['contentDetails']['duration'] ?? null),
                    'embeddable' => isset($item['status']['embeddable']) ? (bool) $item['status']['embeddable'] : null,
                ];
            }
        }

        return $details;
    }

    /**
     * @return array{id: string}|array{handle: string, url: string}|array{url: string}|null
     */
    public static function parseChannelInput(string $input): ?array
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('~(?:^|/channel/)(UC[A-Za-z0-9_-]{22})(?:$|[/?#])~', $input, $m)) {
            return ['id' => $m[1]];
        }

        if (preg_match('~(?:^|youtube\.com/)(@[^/?#\s]+)~u', $input, $m)) {
            $handle = rawurldecode($m[1]);

            return ['handle' => $handle, 'url' => 'https://www.youtube.com/@'.rawurlencode(substr($handle, 1))];
        }

        // older links: youtube.com/c/name, youtube.com/user/name
        if (preg_match('~youtube\.com/((?:c|user)/[^/?#\s]+)~', $input, $m)) {
            return ['url' => 'https://www.youtube.com/'.$m[1]];
        }

        // a bare handle typed without its @
        if (preg_match('~^[\pL\pN._-]{3,}$~u', $input)) {
            return ['handle' => '@'.$input, 'url' => 'https://www.youtube.com/@'.rawurlencode($input)];
        }

        return null;
    }

    /** ISO 8601 duration (PT1H2M3S; P0D while live) → seconds, null when unknown or zero. */
    public static function seconds(?string $duration): ?int
    {
        if (! $duration || ! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $duration, $m)) {
            return null;
        }

        $seconds = ((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600 + ((int) ($m[3] ?? 0)) * 60 + (int) ($m[4] ?? 0);

        return $seconds > 0 ? $seconds : null;
    }

    /** Without an API key, a handle or old link is turned into the UC… id by reading the channel page. */
    private function channelIdFromPage(string $url): string
    {
        $response = $this->send(fn () => Http::timeout(self::TIMEOUT)
            ->withHeaders(['Accept-Language' => 'en'])
            ->get($url));

        if ($response->status() === 404) {
            throw new YouTubeException('not_found');
        }

        $html = (string) $response->body();

        if (preg_match('~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})"~', $html, $m)
            || preg_match('~"externalId":"(UC[A-Za-z0-9_-]{22})"~', $html, $m)) {
            return $m[1];
        }

        throw new YouTubeException('not_found');
    }

    private function feedXml(string $channelId): SimpleXMLElement
    {
        $response = $this->send(fn () => Http::timeout(self::TIMEOUT)->get(self::FEED_URL, ['channel_id' => $channelId]));

        if ($response->status() === 404) {
            throw new YouTubeException('not_found');
        }

        if (! $response->successful()) {
            throw new YouTubeException('feed_failed', (string) $response->status());
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $response->body(), SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new YouTubeException('bad_feed');
        }

        return $xml;
    }

    /** @param  array<string, string>  $query */
    private function api(string $endpoint, array $query): array
    {
        $response = $this->send(fn () => Http::timeout(self::TIMEOUT)
            ->acceptJson()
            ->get(self::API_URL.$endpoint, [...$query, 'key' => config('services.youtube.key')]));

        if (! $response->successful()) {
            throw new YouTubeException('api_failed', (string) ($response->json('error.errors.0.reason') ?? $response->status()));
        }

        return (array) $response->json();
    }

    /** @param  callable(): Response  $request */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            // the exception's message holds the URL (and so the API key): never pass it on
            throw new YouTubeException('unreachable');
        }
    }
}
