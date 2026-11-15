<?php

namespace App\Support\MobileApp;

use App\Models\CmsPage;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Website CMS pages the app shows (admin → Mobile app → Pages), in the admin's order, each in More,
 * on the Services screen or both. Stored in the `mobile.pages` site setting; sent in
 * `app-status.pages`, read with `GET pages/{slug}` (only pages listed here and published).
 */
final class MobilePages
{
    public const PLACEMENTS = ['more', 'services', 'both'];

    /** The app's WebView adds this to its user agent; the website then leaves out its header and footer. */
    public const USER_AGENT = 'MuhraApp';

    /** @return list<array{page_id: int, placement: string}> */
    public static function settings(): array
    {
        $value = SiteSetting::get('mobile.pages');
        $stored = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return self::clean(is_array($stored) ? $stored : []);
    }

    /** @param  array<mixed>  $rows  the admin form's `pages` state */
    public static function save(array $rows): void
    {
        SiteSetting::set('mobile.pages', json_encode(self::clean($rows)), 'text', null, 'mobile_app');
    }

    /**
     * The listed pages that are published, in the request's language.
     *
     * @return list<array{slug: string, title: string, excerpt: string|null, placement: 'more'|'services'|'both'}>
     */
    public static function forClient(): array
    {
        $settings = self::settings();
        if ($settings === []) {
            return [];
        }

        $pages = self::query()->whereIn('id', array_column($settings, 'page_id'))->get()->keyBy('id');
        $locale = app()->getLocale();

        $list = [];
        foreach ($settings as $row) {
            if ($page = $pages->get($row['page_id'])) {
                $list[] = [
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->getTranslation('title', $locale),
                    'excerpt' => $page->getTranslation('excerpt', $locale) ?: null,
                    'placement' => $row['placement'],
                ];
            }
        }

        return $list;
    }

    /** A listed, published page, or null. */
    public static function find(string $slug): ?CmsPage
    {
        return self::query()->where('slug', $slug)->whereIn('id', array_column(self::settings(), 'page_id'))->first();
    }

    /** Whether this website request comes from the app's WebView (`?app=1` on the first load, the user agent after it). */
    public static function inApp(Request $request): bool
    {
        return $request->query('app') === '1' || str_contains((string) $request->userAgent(), self::USER_AGENT);
    }

    /** @return Builder<CmsPage> */
    private static function query()
    {
        return CmsPage::query()->published()->where('is_homepage', false);
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<array{page_id: int, placement: string}>
     */
    private static function clean(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $id = (int) (is_array($row) ? ($row['page_id'] ?? 0) : 0);
            $placement = is_array($row) ? ($row['placement'] ?? null) : null;

            if ($id > 0 && ! in_array($id, array_column($clean, 'page_id'), true)) {
                $clean[] = ['page_id' => $id, 'placement' => in_array($placement, self::PLACEMENTS, true) ? $placement : 'more'];
            }
        }

        return $clean;
    }
}
