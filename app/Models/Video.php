<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Video extends Model
{
    use HasTranslations;

    protected $fillable = ['folder_id', 'title', 'description', 'slug', 'youtube_url', 'order', 'is_active'];

    public array $translatable = ['title', 'description'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(VideoFolder::class, 'folder_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getYoutubeIdAttribute(): ?string
    {
        return YouTube::videoId($this->youtube_url);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://img.youtube.com/vi/{$this->youtube_id}/hqdefault.jpg" : null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://www.youtube-nocookie.com/embed/{$this->youtube_id}" : null;
    }
}
