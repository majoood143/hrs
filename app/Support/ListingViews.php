<?php

namespace App\Support;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\AppEvent;
use App\Models\BookingSlot;
use App\Models\Center;
use App\Models\Clinic;
use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\Jockey;
use App\Models\Shop;
use App\Models\Stable;
use App\Models\Stallion;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The figures behind the listings' "Views" widgets in the admin, as plain arrays cached for
 * CachedWidgetCounts::TTL_MINUTES. `views_count` is every view (website + app, Support\ViewCounter);
 * the per-day figures come from the app's `listing_viewed` events (the website keeps no history).
 */
class ListingViews
{
    public const DAYS = 30;

    /** Model => [the app's section, the key it sends] (as in Api\V1\ViewController). */
    public const SECTIONS = [
        HorseSalePost::class => ['horses-for-sale', 'id'],
        ToolSalePost::class => ['tools-for-sale', 'id'],
        TransferPost::class => ['transfer-board', 'id'],
        Farrier::class => ['farriers', 'id'],
        Jockey::class => ['jockeys', 'id'],
        Stallion::class => ['stallions', 'id'],
        Stable::class => ['stables', 'slug'],
        Clinic::class => ['clinics', 'slug'],
        Center::class => ['centers', 'slug'],
        Shop::class => ['shops', 'slug'],
    ];

    /**
     * @param  class-string<Model>  $model
     * @return array{total: int, listings: int, unseen: int, top: list<array{id: int, views: int}>}
     */
    public static function summary(string $model): array
    {
        return CachedWidgetCounts::remember('views.'.class_basename($model).'.summary', function () use ($model) {
            $row = $model::query()->toBase()
                ->selectRaw('count(*) as listings, coalesce(sum(views_count), 0) as total')
                ->selectRaw('sum(case when views_count = 0 then 1 else 0 end) as unseen')
                ->first();

            return [
                'total' => (int) $row->total,
                'listings' => (int) $row->listings,
                'unseen' => (int) $row->unseen,
                'top' => $model::query()->where('views_count', '>', 0)->orderByDesc('views_count')->limit(5)->toBase()
                    ->get(['id', 'views_count'])->map(fn ($r) => ['id' => (int) $r->id, 'views' => (int) $r->views_count])->all(),
            ];
        });
    }

    /** Where one listing stands on its board. @return array{rank: int, of: int, share: float} */
    public static function rank(Model $record): array
    {
        $views = (int) $record->getAttribute('views_count');
        $summary = self::summary($record::class);

        return [
            'rank' => $record->newQuery()->where('views_count', '>', $views)->count() + 1,
            'of' => $summary['listings'],
            'share' => $summary['total'] > 0 ? round(100 * $views / $summary['total'], 1) : 0.0,
        ];
    }

    /**
     * App views per (Oman) day over the last DAYS days, plus the DAYS before, for a board or one listing.
     *
     * @return array{labels: list<string>, views: list<int>, total: int, before: int}
     */
    public static function appDaily(string $model, ?Model $record = null): array
    {
        [$section, $column] = self::SECTIONS[$model];
        $key = $record?->getAttribute($column);

        return CachedWidgetCounts::remember('views.'.class_basename($model).'.daily.'.($key ?? 'all').'.'.app()->getLocale(), function () use ($section, $key) {
            $zone = BookingSlot::timezone();
            $first = CarbonImmutable::now($zone)->startOfDay()->subDays(self::DAYS - 1);
            $days = collect(range(0, self::DAYS - 1))->map(fn (int $i) => $first->addDays($i)->toDateString());
            $events = AppEvent::query()
                ->where('name', 'listing_viewed')
                ->where('properties->section', $section)
                ->when($key !== null, fn ($q) => $q->where('properties->id', (string) $key));
            // shortcut: buckets in PHP (portable across MySQL/SQLite and Oman's day), like PushInsights; move to SQL past ~100k views a month
            $byDay = (clone $events)->where('occurred_at', '>=', $first->utc())->pluck('occurred_at')
                ->countBy(fn ($at) => CarbonImmutable::parse($at, 'UTC')->setTimezone($zone)->toDateString());

            return [
                'labels' => $days->map(fn (string $d) => CarbonImmutable::parse($d)->translatedFormat('j M'))->all(),
                'views' => $days->map(fn (string $d) => (int) ($byDay[$d] ?? 0))->all(),
                'total' => (int) $byDay->sum(),
                'before' => (clone $events)->where('occurred_at', '>=', $first->subDays(self::DAYS)->utc())->where('occurred_at', '<', $first->utc())->count(),
            ];
        });
    }

    /** A listing's name for the admin (transfer posts have none: their route). */
    public static function title(Model $record): string
    {
        if ($record instanceof TransferPost) {
            $record->loadMissing('fromCity', 'toCity');

            return '#'.$record->getKey().' · '.trim(($record->fromCity?->name ?? '').' → '.($record->toCity?->name ?? ''));
        }

        return (string) ($record->getAttribute('name') ?: '#'.$record->getKey());
    }
}
