<?php

namespace App\Support\Ads;

use App\Models\Ad;
use App\Models\BookingSlot;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts promotion impressions (each time an ad is served: a website page with an "Ads area" block,
 * or the app's `GET home` / `GET ads/{placement}`) and clicks (`/promo/{ad}/go`) in `ad_daily_stats`.
 */
class AdStats
{
    public const SOURCES = ['web', 'app'];

    /**
     * shortcut: an impression = the ad was served (crawlers and slider slides never scrolled to count too);
     * move to a client-side "seen" beacon if advertisers need viewable impressions.
     *
     * @param  iterable<Ad>  $ads
     */
    public static function impressions(iterable $ads, string $source): void
    {
        foreach ($ads as $ad) {
            self::add($ad->getKey(), $source, 'impressions');
        }
    }

    public static function click(Ad $ad, string $source): void
    {
        self::add($ad->getKey(), $source, 'clicks');
    }

    private static function add(int $adId, string $source, string $column): void
    {
        $source = in_array($source, self::SOURCES, true) ? $source : 'web';

        try {
            DB::table('ad_daily_stats')->upsert(
                [['ad_id' => $adId, 'date' => now(BookingSlot::timezone())->toDateString(), 'source' => $source, $column => 1]],
                ['ad_id', 'date', 'source'],
                [$column => DB::raw("{$column} + 1")],
            );
        } catch (Throwable $e) {
            // counting must never break the page or the click's redirect
            report($e);
        }
    }
}
