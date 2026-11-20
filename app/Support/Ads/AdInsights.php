<?php

namespace App\Support\Ads;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Ad;
use App\Models\BookingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind admin → Promotions' widgets, as plain cached arrays. `$adId` null = all promotions.
 * Days are Oman's (AdStats writes them so).
 */
class AdInsights
{
    public const DAYS = 30;

    /** @return array{impressions: int, impressions_before: int, clicks: int, clicks_before: int, running: int} */
    public static function summary(?int $adId = null): array
    {
        return CachedWidgetCounts::remember('ads.summary.'.($adId ?? 'all'), function () use ($adId) {
            [$from, $before] = self::periods();
            $sum = fn (string $start, ?string $end = null) => (array) self::query($adId)->where('date', '>=', $start)
                ->when($end, fn ($q) => $q->where('date', '<', $end))
                ->selectRaw('coalesce(sum(impressions), 0) as impressions, coalesce(sum(clicks), 0) as clicks')->first();
            $now = $sum($from);
            $prev = $sum($before, $from);

            return [
                'impressions' => (int) $now['impressions'],
                'impressions_before' => (int) $prev['impressions'],
                'clicks' => (int) $now['clicks'],
                'clicks_before' => (int) $prev['clicks'],
                'running' => Ad::query()->active()->when($adId, fn ($q) => $q->whereKey($adId))->count(),
            ];
        });
    }

    /** @return array{labels: list<string>, impressions: list<int>, clicks: list<int>} */
    public static function daily(?int $adId = null): array
    {
        return CachedWidgetCounts::remember('ads.daily.'.($adId ?? 'all'), function () use ($adId) {
            $first = CarbonImmutable::now(BookingSlot::timezone())->startOfDay()->subDays(self::DAYS - 1);
            $days = collect(range(0, self::DAYS - 1))->map(fn (int $i) => $first->addDays($i)->toDateString());
            $rows = self::query($adId)->where('date', '>=', $first->toDateString())
                ->selectRaw('date, sum(impressions) as impressions, sum(clicks) as clicks')->groupBy('date')->get()
                ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

            return [
                'labels' => $days->map(fn (string $d) => CarbonImmutable::parse($d)->translatedFormat('j M'))->all(),
                'impressions' => $days->map(fn (string $d) => (int) ($rows[$d]->impressions ?? 0))->all(),
                'clicks' => $days->map(fn (string $d) => (int) ($rows[$d]->clicks ?? 0))->all(),
            ];
        });
    }

    /** Impressions and clicks per source over the last DAYS days. @return array<string, array{impressions: int, clicks: int}> */
    public static function sources(?int $adId = null): array
    {
        return CachedWidgetCounts::remember('ads.sources.'.($adId ?? 'all'), function () use ($adId) {
            $rows = self::query($adId)->where('date', '>=', self::periods()[0])
                ->selectRaw('source, sum(impressions) as impressions, sum(clicks) as clicks')->groupBy('source')->get()->keyBy('source');

            return collect(AdStats::SOURCES)->mapWithKeys(fn (string $s) => [$s => [
                'impressions' => (int) ($rows[$s]->impressions ?? 0),
                'clicks' => (int) ($rows[$s]->clicks ?? 0),
            ]])->all();
        });
    }

    /** The promotions with the most clicks over the last DAYS days. @return list<array{id: int, name: string, impressions: int, clicks: int}> */
    public static function top(int $limit = 5): array
    {
        return CachedWidgetCounts::remember("ads.top.{$limit}", function () use ($limit) {
            return self::query()->join('ads', 'ads.id', '=', 'ad_daily_stats.ad_id')
                ->where('date', '>=', self::periods()[0])
                ->selectRaw('ads.id, ads.customer_name as name, sum(ad_daily_stats.impressions) as impressions, sum(ad_daily_stats.clicks) as clicks')
                ->groupBy('ads.id', 'ads.customer_name')
                ->orderByDesc('clicks')->orderByDesc('impressions')->limit($limit)->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name, 'impressions' => (int) $row->impressions, 'clicks' => (int) $row->clicks])
                ->all();
        });
    }

    /** Change in % from the period before, null when there was nothing before. */
    public static function change(int $now, int $before): ?float
    {
        return $before > 0 ? round(100 * ($now - $before) / $before, 1) : null;
    }

    public static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 2) : 0.0;
    }

    private static function query(?int $adId = null)
    {
        return DB::table('ad_daily_stats')->when($adId, fn ($q) => $q->where('ad_daily_stats.ad_id', $adId));
    }

    /** @return array{0: string, 1: string} the first day of the last DAYS days, and of the DAYS before */
    private static function periods(): array
    {
        $today = CarbonImmutable::now(BookingSlot::timezone())->startOfDay();

        return [$today->subDays(self::DAYS - 1)->toDateString(), $today->subDays(2 * self::DAYS - 1)->toDateString()];
    }
}
