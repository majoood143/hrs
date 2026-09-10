<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Spatie\Translatable\HasTranslations;

class CmsPost extends Model implements HasMedia
{
    use HasTags;
    use HasTranslations;
    use InteractsWithMedia;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'category_id', 'status', 'published_at',
        'template', 'container_width', 'show_title', 'custom_css',
        'meta_title', 'meta_description', 'canonical_url',
    ];

    public array $translatable = ['title', 'excerpt', 'meta_title', 'meta_description'];

    protected $casts = [
        'content' => 'array',
        'published_at' => 'datetime',
        'show_title' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')->singleFile();
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('og_image')->singleFile();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'category_id');
    }

    public function featuredImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('featured_image') ?: null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            if ($post->status === 'published' && $post->published_at === null) {
                $post->published_at = now();
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
