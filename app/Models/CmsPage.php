<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class CmsPage extends Model implements HasMedia
{
    /**
     * The site's own sections: `/{slug}` (routes/web.php) never serves a slug that starts with one
     * of these, so a page there only gives that section its SEO texts (Seo::forSlug), when it is
     * exactly the section's name. Keep the route and CmsPageResource on this list.
     */
    public const SECTIONS = ['transfer-board', 'horses-for-sale', 'stables', 'clinics', 'centers', 'shops', 'farriers', 'jockeys', 'tools-for-sale', 'events', 'video-library', 'racing'];

    /** Never a page, not even for SEO. */
    public const RESERVED = ['admin', 'transportation', 'blog'];

    /** Whether a page may take this slug: one `/{slug}` serves, or exactly a section's name (its SEO texts). */
    public static function slugIsAllowed(string $slug): bool
    {
        foreach ([...self::RESERVED, ...self::SECTIONS] as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return in_array($slug, self::SECTIONS, true);
            }
        }

        return true;
    }

    use HasTranslations;
    use InteractsWithMedia;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'status', 'published_at',
        'template', 'layout', 'is_homepage', 'is_system', 'container_width', 'show_title',
        'custom_css', 'custom_head_scripts', 'custom_body_scripts', 'meta_title', 'meta_description', 'canonical_url',
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
