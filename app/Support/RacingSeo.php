<?php

namespace App\Support;

use App\Models\SiteSetting;

class RacingSeo
{
    /**
     * Search results, profiles, race days and rating lists are thin, near-infinite URLs,
     * so they are kept out of search engines.
     *
     * @return array{seoTitle: string, seoDescription: ?string, seoImage: null, canonicalUrl: null, noindex: true, nofollow: false, ogType: string}
     */
    public static function for(string $title, ?string $description = null): array
    {
        return [
            'seoTitle' => $title . ' — ' . SiteSetting::siteName(),
            // no image, but a description is what WhatsApp / X / Facebook show under the title when a page is shared
            'seoDescription' => $description !== null && trim($description) !== '' ? mb_substr(trim($description), 0, 200) : null,
            'seoImage' => null,
            'canonicalUrl' => null,
            'noindex' => true,
            'nofollow' => false,
            'ogType' => 'website',
        ];
    }
}
