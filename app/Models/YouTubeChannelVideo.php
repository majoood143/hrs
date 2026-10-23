<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A video found on a watched channel (the admin "YouTube inbox"). Admins add it to the video
 * library by hand (`library_video_id` then points at the Video) or ignore it.
 */
class YouTubeChannelVideo extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_ADDED = 'added';

    public const STATUS_IGNORED = 'ignored';

    /** Shorts on a channel that skips them: kept only so they are not looked at again. */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [self::STATUS_NEW, self::STATUS_ADDED, self::STATUS_IGNORED, self::STATUS_SKIPPED];

    public const KINDS = ['video', 'short', 'live', 'upcoming', 'stream'];

    protected $table = 'youtube_channel_videos';

    protected $fillable = [
        'youtube_channel_id', 'youtube_id', 'title', 'description', 'thumbnail_url', 'kind',
        'published_at', 'scheduled_at', 'duration_seconds', 'embeddable', 'status', 'library_video_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'notified_at' => 'datetime',
        'duration_seconds' => 'integer',
        'embeddable' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(YouTubeChannel::class, 'youtube_channel_id');
    }

    public function libraryVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'library_video_id');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function watchUrl(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtube_id;
    }

    /**
     * The New video form's fields, filled in from YouTube: the title and description go in the
     * language they are written in (Arabic letters → ar, else en), the admin adds the other.
     *
     * @return array<string, mixed>
     */
    public function libraryFormData(): array
    {
        $locale = preg_match('/\p{Arabic}/u', $this->title) ? 'ar' : 'en';
        $slug = $locale === 'en' ? Str::slug($this->title) : '';

        return [
            'folder_id' => $this->channel?->video_folder_id,
            'title' => [$locale => $this->title],
            'description' => [$locale => self::paragraphs($this->description)],
            'slug' => $slug !== '' ? $slug : Str::lower($this->youtube_id),
            'youtube_url' => $this->watchUrl(),
        ];
    }

    /**
     * Adds it to the channel's library folder by itself (the channel's `auto_add`). Unlike the
     * New video form no admin writes the other language, so the title and description go in every
     * language: a video with no English title would show untitled on the English site and app.
     */
    public function addToLibrary(bool $publish): Video
    {
        $form = $this->libraryFormData();
        $everyLanguage = fn (string $value) => collect(config('languages.available', ['en' => [], 'ar' => []]))
            ->map(fn () => $value)
            ->all();
        $description = reset($form['description']);

        $video = Video::query()->create(array_filter([
            'folder_id' => $form['folder_id'],
            'title' => $everyLanguage($this->title),
            'description' => $description === null ? null : $everyLanguage($description),
            'slug' => self::uniqueSlug($form['slug']),
            'youtube_url' => $form['youtube_url'],
            'is_active' => $publish,
        ], fn ($value) => $value !== null));

        $this->forceFill(['status' => self::STATUS_ADDED, 'library_video_id' => $video->getKey()])->save();

        return $video;
    }

    private static function uniqueSlug(string $slug): string
    {
        $candidate = $slug;

        for ($n = 2; Video::query()->where('slug', $candidate)->exists(); $n++) {
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }

    /** YouTube's plain-text description as escaped HTML paragraphs, or null. */
    private static function paragraphs(?string $text): ?string
    {
        $blocks = preg_split('/\R{2,}/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);

        return $blocks === []
            ? null
            : implode('', array_map(fn (string $block) => '<p>'.nl2br(e(trim($block)), false).'</p>', $blocks));
    }

    /** "1:02:03" / "4:05", or null when unknown (or a stream that has not ended). */
    public function durationLabel(): ?string
    {
        $seconds = $this->duration_seconds;

        if (! $seconds) {
            return null;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
