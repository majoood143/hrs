<?php

namespace App\Support\MobileApp;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\AppEvent;
use App\Models\BookingSlot;
use App\Models\MobileAnnouncement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The figures behind admin → Mobile app → Announcements' widgets, from the app's events
 * (`announcement_shown` / `_clicked` / `_dismissed`, property `id`), as plain arrays cached for
 * CachedWidgetCounts::TTL_MINUTES. Days are Oman's. Apps older than the update that sends them count nothing.
 */
class AnnouncementInsights
{
    public const DAYS = 30;

    public const EVENTS = ['shown' => 'announcement_shown', 'clicked' => 'announcement_clicked', 'dismissed' => 'announcement_dismissed'];

    /**
     * The last DAYS days against the DAYS before; one announcement when `$id` is given.
     *
     * @return array{shown: int, shown_before: int, clicked: int, clicked_before: int, dismissed: int, reach: int, live: int}
     */
    public static function summary(?int $id = null): array
    {
        return CachedWidgetCounts::remember('announcements.summary.'.($id ?? 'all'), function () use ($id) {
            $from = now()->subDays(self::DAYS);
            $before = $from->copy()->subDays(self::DAYS);
            [$now, $prev] = self::events($id, $before)->partition(fn ($row) => CarbonImmutable::parse($row->occurred_at, 'UTC')->gte($from));
            $count = fn ($rows, string $kind) => $rows->where('name', self::EVENTS[$kind])->count();

            return [
                'shown' => $count($now, 'shown'),
                'shown_before' => $count($prev, 'shown'),
                'clicked' => $count($now, 'clicked'),
                'clicked_before' => $count($prev, 'clicked'),
                'dismissed' => $count($now, 'dismissed'),
                'reach' => $now->where('name', self::EVENTS['shown'])->pluck('install_id')->filter()->unique()->count(),
                'live' => MobileAnnouncement::query()->live()->count(),
            ];
        });
    }

    /** Shown and clicked per day over the last DAYS days. @return array{labels: list<string>, shown: list<int>, clicked: list<int>} */
    public static function daily(?int $id = null): array
    {
        return CachedWidgetCounts::remember('announcements.daily.'.($id ?? 'all').'.'.app()->getLocale(), function () use ($id) {
            $zone = BookingSlot::timezone();
            $first = CarbonImmutable::now($zone)->startOfDay()->subDays(self::DAYS - 1);
            $days = collect(range(0, self::DAYS - 1))->map(fn (int $i) => $first->addDays($i)->toDateString());
            $rows = self::events($id, $first->utc());
            $perDay = fn (string $kind) => $rows->where('name', self::EVENTS[$kind])
                ->countBy(fn ($row) => CarbonImmutable::parse($row->occurred_at, 'UTC')->setTimezone($zone)->toDateString());
            $shown = $perDay('shown');
            $clicked = $perDay('clicked');

            return [
                'labels' => $days->map(fn (string $d) => CarbonImmutable::parse($d)->translatedFormat('j M'))->all(),
                'shown' => $days->map(fn (string $d) => (int) ($shown[$d] ?? 0))->all(),
                'clicked' => $days->map(fn (string $d) => (int) ($clicked[$d] ?? 0))->all(),
            ];
        });
    }

    /** All-time figures per announcement id (the list's columns). @return array<int, array{shown: int, clicked: int, dismissed: int}> */
    public static function totals(): array
    {
        return CachedWidgetCounts::remember('announcements.totals', fn () => self::events(null, null)
            ->groupBy(fn ($row) => (int) (json_decode((string) $row->properties, true)['id'] ?? 0))
            ->map(fn (Collection $rows) => collect(self::EVENTS)->map(fn (string $name) => $rows->where('name', $name)->count())->all())
            ->all());
    }

    public static function change(int $now, int $before): ?float
    {
        return $before > 0 ? round(100 * ($now - $before) / $before, 1) : null;
    }

    public static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : 0.0;
    }

    /** @return Collection<int, object{name: string, install_id: ?string, properties: ?string, occurred_at: string}> */
    private static function events(?int $id, ?\DateTimeInterface $since): Collection
    {
        // shortcut: rows filtered in PHP (portable across MySQL/SQLite and Oman's day), like PushInsights; move to SQL past ~100k popups a month
        return AppEvent::query()->toBase()
            ->whereIn('name', self::EVENTS)
            ->when($since !== null, fn ($q) => $q->where('occurred_at', '>=', $since))
            ->when($id !== null, fn ($q) => $q->where('properties->id', (string) $id))
            ->get(['name', 'install_id', 'properties', 'occurred_at']);
    }
}
