<?php

namespace App\Support;

use App\Models\CmsPage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

class Seo
{
    /**
     * Build SEO view data for a hardcoded site route (transfer-board, stables, ...)
     * whose metadata is controlled through a matching published CmsPage record.
     *
     * @return array{seoTitle: string, seoDescription: ?string, seoImage: ?string, canonicalUrl: ?string, noindex: bool, nofollow: bool, ogType: string}
     */
    public static function forSlug(string $slug, string $fallbackTitle): array
    {
        $page = CmsPage::query()->where('slug', $slug)->published()->first();

        return static::build($page, $fallbackTitle);
    }

    /**
     * Build SEO view data for a CMS-driven page.
     *
     * @return array{seoTitle: string, seoDescription: ?string, seoImage: ?string, canonicalUrl: ?string, noindex: bool, nofollow: bool, ogType: string}
     */
    public static function forPage(CmsPage $page): array
    {
        return static::build($page, $page->getTranslation('title', app()->getLocale()));
    }

    protected static function build(?CmsPage $page, string $fallbackTitle): array
    {
        $locale = app()->getLocale();

        return [
            'seoTitle' => $page?->getTranslation('meta_title', $locale)
                ?: $page?->getTranslation('title', $locale)
                ?: $fallbackTitle,
            'seoDescription' => $page?->getTranslation('meta_description', $locale)
                ?: $page?->getTranslation('excerpt', $locale)
                ?: SiteSetting::get('default_meta_description')
                ?: SiteSetting::get('seo_meta_description')
                ?: null,
            'seoImage' => $page?->getFirstMediaUrl('og_image')
                ?: $page?->getFirstMediaUrl('featured_image')
                ?: static::defaultImage(),
            'canonicalUrl' => $page?->canonical_url,
            'noindex' => (bool) $page?->noindex,
            'nofollow' => (bool) $page?->nofollow,
            'ogType' => $page?->og_type ?: 'website',
        ];
    }

    public static function defaultImage(): ?string
    {
        $path = SiteSetting::get('default_og_image') ?: SiteSetting::get('seo_og_image');

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
