<?php

namespace App\Support\MobileApp;

use App\Models\Ad;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Which ad zone the app shows where (admin → Mobile app → Ad zones), stored in the `mobile.ads` site
 * setting as placement => zone id. A placement without a zone shows no ads, except Home, which then
 * falls back to the website homepage's "Ads area" block (HomeController). All behind the `promos` feature.
 */
final class MobileAds
{
    /** Home, then the tops of the horses, tools and transfer boards. */
    public const PLACEMENTS = ['home', 'horses', 'tools', 'transfers'];

    private const MAX_ADS = 8;

    /** @return array<string, int|null> placement => zone id */
    public static function settings(): array
    {
        $value = SiteSetting::get('mobile.ads');
        $stored = is_string($value) && $value !== '' ? json_decode($value, true) : null;
        $stored = is_array($stored) ? $stored : [];

        return collect(self::PLACEMENTS)->mapWithKeys(fn (string $key) => [
            $key => is_numeric($stored[$key] ?? null) ? (int) $stored[$key] : null,
        ])->all();
    }

    /** @param  array<string, mixed>  $state  the admin form's `ads` state */
    public static function save(array $state): void
    {
        $zones = collect(self::PLACEMENTS)->mapWithKeys(fn (string $key) => [
            $key => is_numeric($state[$key] ?? null) ? (int) $state[$key] : null,
        ])->all();

        SiteSetting::set('mobile.ads', json_encode($zones), 'text', null, 'mobile_app');
    }

    /** The placement's zone, unless none is picked (null). */
    public static function zoneFor(string $placement): ?int
    {
        return self::settings()[$placement] ?? null;
    }

    /**
     * The running ads of an active zone that have an image or a video, in the zone's order.
     *
     * @return Collection<int, Ad>
     */
    public static function ads(int $zoneId, int $count = self::MAX_ADS): Collection
    {
        return Ad::query()->active()
            ->where('zone_id', $zoneId)
            ->whereHas('zone', fn ($q) => $q->active())
            ->where(fn ($q) => $q->whereNotNull('image_path')->orWhereNotNull('video_path'))
            ->orderBy('order')
            ->take($count)
            ->get();
    }
}
