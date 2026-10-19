<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public YouTube channel we watch (not ours): `youtube:check` reads its feed and alerts the
 * admins by WhatsApp about each new video (App\Services\YouTube\ChannelWatcher).
 */
class YouTubeChannel extends Model
{
    protected $table = 'youtube_channels';

    protected $fillable = ['channel_id', 'handle', 'title', 'thumbnail_url', 'video_folder_id', 'skip_shorts', 'is_active'];

    /** The table's defaults, so a channel just created behaves the same before it is reloaded. */
    protected $attributes = [
        'skip_shorts' => true,
        'is_active' => true,
    ];

    protected $casts = [
        'skip_shorts' => 'boolean',
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

    public function url(): string
    {
        return $this->handle
            ? 'https://www.youtube.com/'.$this->handle
            : 'https://www.youtube.com/channel/'.$this->channel_id;
    }
}
