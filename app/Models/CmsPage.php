<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class CmsPage extends Model implements HasMedia
{
    use HasTranslations;
    use InteractsWithMedia;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'status', 'published_at',
        'template', 'layout', 'is_homepage', 'is_system', 'container_width', 'show_title',
        'custom_css', 'meta_title', 'meta_description', 'canonical_url',
        'noindex', 'nofollow', 'og_type',
    ];

    public array $translatable = ['title', 'excerpt', 'meta_title', 'meta_description'];

    protected $casts = [
        'content' => 'array',
        'published_at' => 'datetime',
        'is_homepage' => 'boolean',
        'is_system' => 'boolean',
        'show_title' => 'boolean',
        'noindex' => 'boolean',
        'nofollow' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')->singleFile();
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('og_image')->singleFile();
    }

    public function menuItems()
    {
        return $this->hasMany(CmsMenuItem::class, 'page_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function canBeDeleted(): bool
    {
        return ! $this->is_homepage && ! $this->is_system;
    }

    public function featuredImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('featured_image') ?: null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $page) {
            if ($page->status === 'published' && $page->published_at === null) {
                $page->published_at = now();
            }
        });
    }
}
