<?php

namespace App\Support\MobileApp;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\AppEvent;
use App\Models\BookingSlot;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The figures behind admin → Mobile app → Notifications' widgets (reach, sends, opens), as plain
 * arrays cached for CachedWidgetCounts::TTL_MINUTES. Days are Oman's, not the server's UTC.
 */
class PushInsights
{
    public const DAYS = 30;

    /** @return array{reachable: int, active: int, sent: int, sent_before: int, opened: int, opened_before: int, targeted: int, accepted: int, campaign_opens: int} */
    public static function summary(): array
    {
        return CachedWidgetCounts::remember('push.summary', function () {
            $now = now();
            $from = $now->copy()->subDays(self::DAYS);
            $before = $from->copy()->subDays(self::DAYS);
            $deliveries = fn ($start, $end = null) => PushDelivery::query()->where('created_at', '>=', $start)->when($end, fn ($q) => $q->where('created_at', '<', $end));
            $campaigns = PushCampaign::query()->where('status', 'sent')->where('sent_at', '>=', $from);

            return [
                'reachable' => MobileDevice::query()->where('enabled', true)->count(),
                'active' => MobileDevice::query()->where('enabled', true)->where('last_seen_at', '>=', $now->copy()->subDays(7))->count(),
                'sent' => $deliveries($from)->count(),
                'sent_before' => $deliveries($before, $from)->count(),
                'opened' => $deliveries($from)->whereNotNull('opened_at')->count(),
                'opened_before' => $deliveries($before, $from)->whereNotNull('opened_at')->count(),
                'targeted' => (int) (clone $campaigns)->sum('recipients'),
                'accepted' => (int) (clone $campaigns)->sum('accepted'),
                'campaign_opens' => (int) (clone $campaigns)->sum('opened'),
            ];
        });
    }

    /**
     * Phones (install ids) that opened the app in the last DAYS days, and each one's latest
     * notification permission as the app reported it (`push_permission`), so the admin sees why reach is smaller.
     *
     * @return array{installs: int, granted: int, denied: int, undetermined: int}
     */
    public static function permissions(): array
    {
        return CachedWidgetCounts::remember('push.permissions', function () {
            $from = now()->subDays(self::DAYS);
            $latest = AppEvent::query()->where('name', 'push_permission')->whereNotNull('install_id')
                ->where('occurred_at', '>=', $from)->selectRaw('max(id)')->groupBy('install_id');
            $status = fn (string $value) => AppEvent::query()->whereIn('id', $latest)->where('properties->status', $value)->count();

            return [
                'installs' => AppEvent::query()->whereNotNull('install_id')->where('occurred_at', '>=', $from)->distinct()->count('install_id'),
                'granted' => $status('granted'),
                'denied' => $status('denied'),
                'undetermined' => $status('undetermined'),
            ];
        });
    }

    /**
     * Phones lost in the last DAYS days: `uninstalled` = Apple/Google said the app is gone (push
     * phones only), `lapsed` = installs that opened the app in the DAYS before but not since (everyone).
     *
     * @return array{uninstalled: int, lapsed: int}
     */
    public static function losses(): array
    {
        return CachedWidgetCounts::remember('push.losses', function () {
            $from = now()->subDays(self::DAYS);
            $seen = fn ($start, $end = null) => AppEvent::query()->whereNotNull('install_id')->where('occurred_at', '>=', $start)
                ->when($end, fn ($q) => $q->where('occurred_at', '<', $end));

            return [
                'uninstalled' => MobileDevice::query()->withoutGlobalScope('installed')->where('uninstalled_at', '>=', $from)->count(),
                'lapsed' => $seen($from->copy()->subDays(self::DAYS), $from)->whereNotIn('install_id', $seen($from)->select('install_id'))
                    ->distinct()->count('install_id'),
            ];
        });
    }

    /** Sends and opens per day over the last DAYS days. @return array{labels: list<string>, sent: list<int>, opened: list<int>} */
    public static function daily(): array
    {
        return CachedWidgetCounts::remember('push.daily', function () {
            $zone = BookingSlot::timezone();
            $first = CarbonImmutable::now($zone)->startOfDay()->subDays(self::DAYS - 1);
            $days = collect(range(0, self::DAYS - 1))->map(fn (int $i) => $first->addDays($i)->toDateString());
            // shortcut: buckets in PHP (portable across MySQL/SQLite and Oman's day); move to SQL if deliveries reach millions a month
            $count = fn (string $column) => PushDelivery::query()->where($column, '>=', $first->utc())->pluck($column)
                ->countBy(fn ($at) => CarbonImmutable::parse($at, 'UTC')->setTimezone($zone)->toDateString());
            $sent = $count('created_at');
            $opened = $count('opened_at');

            return [
                'labels' => $days->map(fn (string $d) => CarbonImmutable::parse($d)->translatedFormat('j M'))->all(),
                'sent' => $days->map(fn (string $d) => (int) ($sent[$d] ?? 0))->all(),
                'opened' => $days->map(fn (string $d) => (int) ($opened[$d] ?? 0))->all(),
            ];
        });
    }

    /** The phones that take notifications, by who is signed in, platform and topic. */
    public static function audience(): array
    {
        return CachedWidgetCounts::remember('push.audience', function () {
            $enabled = fn () => MobileDevice::query()->where('enabled', true);

            return [
                'customers' => $enabled()->whereNotNull('customer_token_id')->count(),
                'owners' => $enabled()->whereNotNull('owner_token_id')->count(),
                'guests' => $enabled()->whereNull('customer_token_id')->whereNull('owner_token_id')->count(),
                'platforms' => $enabled()->toBase()->selectRaw('platform, count(*) as total')->groupBy('platform')->pluck('total', 'platform')->map(fn ($n) => (int) $n)->all(),
                // a phone with no topics saved takes them all (like PushCampaign::devices())
                'topics' => collect(MobileDevice::TOPICS)->mapWithKeys(fn (string $topic) => [$topic => $enabled()
                    ->where(fn (Builder $q) => $q->whereNull('topics')->orWhereJsonContains('topics', $topic))->count()])->all(),
            ];
        });
    }

    /** One sent campaign's opens per hour over its first 48 hours. @return array{labels: list<string>, opened: list<int>} */
    public static function campaignOpens(PushCampaign $campaign): array
    {
        return CachedWidgetCounts::remember("push.campaign.{$campaign->getKey()}.opens", function () use ($campaign) {
            $hours = 48;
            $start = $campaign->sent_at;
            $byHour = $campaign->deliveries()->whereNotNull('opened_at')->pluck('opened_at')
                ->countBy(fn ($at) => (int) floor($start->diffInMinutes(CarbonImmutable::parse($at, 'UTC')) / 60));

            return [
                'labels' => array_map(fn (int $h) => '+'.$h.'h', range(0, $hours - 1)),
                'opened' => array_map(fn (int $h) => (int) ($byHour[$h] ?? 0), range(0, $hours - 1)),
            ];
        });
    }

    /** Change in % from the period before, null when there was nothing before. */
    public static function change(int $now, int $before): ?float
    {
        return $before > 0 ? round(100 * ($now - $before) / $before, 1) : null;
    }

    public static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : 0.0;
    }
}
