<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public YouTube channel we watch (not ours): `youtube:check` reads its feed, puts each new
 * video in the inbox or straight into the library (`auto_add`) and alerts the admins by WhatsApp
 * (`notify`) (App\Services\YouTube\ChannelWatcher).
 */
class YouTubeChannel extends Model
{
    /** New videos wait in the inbox for an admin. */
    public const AUTO_ADD_OFF = 'off';

    /** New videos are added to the library hidden, for an admin to publish. */
    public const AUTO_ADD_HIDDEN = 'hidden';

    /** New videos are added to the library and shown right away. */
    public const AUTO_ADD_PUBLISH = 'publish';

    public const AUTO_ADD_MODES = [self::AUTO_ADD_OFF, self::AUTO_ADD_HIDDEN, self::AUTO_ADD_PUBLISH];

    protected $table = 'youtube_channels';

    protected $fillable = ['channel_id', 'handle', 'title', 'thumbnail_url', 'video_folder_id', 'skip_shorts', 'auto_add', 'notify', 'is_active'];

    /** The table's defaults, so a channel just created behaves the same before it is reloaded. */
    protected $attributes = [
        'skip_shorts' => true,
        'auto_add' => self::AUTO_ADD_OFF,
        'notify' => true,
        'is_active' => true,
    ];

    protected $casts = [
        'skip_shorts' => 'boolean',
        'notify' => 'boolean',
        'is_active' => 'boolean',
        'checked_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(VideoFolder::class, 'video_folder_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(YouTubeChannelVideo::class, 'youtube_channel_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether new videos go into the library by themselves (they need a folder to go in). */
    public function autoAdds(): bool
    {
        return $this->auto_add !== self::AUTO_ADD_OFF && $this->video_folder_id !== null;
    }

    public function url(): string
    {
        return $this->handle
            ? 'https://www.youtube.com/'.$this->handle
            : 'https://www.youtube.com/channel/'.$this->channel_id;
    }
}
