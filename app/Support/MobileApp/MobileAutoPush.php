<?php

namespace App\Support\MobileApp;

use App\Models\Center;
use App\Models\Clinic;
use App\Models\CmsPost;
use App\Models\Event;
use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\Jockey;
use App\Models\MobileAnnouncement;
use App\Models\PushCampaign;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use App\Models\Video;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Str;
use Throwable;

/**
 * Automatic notifications (admin → Mobile app → Automatic notifications, the `mobile.auto_push` site
 * setting; every section off by default): new content going live becomes a PushCampaign due now, which
 * `push:send-due` sends within a minute, with the campaigns' audience rules, inbox and stats.
 */
final class MobileAutoPush
{
    public const DEFAULT_DAILY_CAP = 3;

    public const MAX_DAILY_CAP = 20;

    /** section => [model, the scope that means live, feature, topic, website page (null = the feature's app screen)] */
    public const SECTIONS = [
        'horses' => [HorseSalePost::class, 'visible', 'horses_for_sale', 'marketplace', 'horses-for-sale.show'],
        'tools' => [ToolSalePost::class, 'visible', 'tools_for_sale', 'marketplace', 'tools-for-sale.show'],
        'transfers' => [TransferPost::class, 'visible', 'transfer_board', 'services', 'transfer-board.show'],
        'farriers' => [Farrier::class, 'visible', 'farriers', 'services', 'farriers.show'],
        'jockeys' => [Jockey::class, 'visible', 'jockeys', 'services', 'jockeys.show'],
        'stables' => [Stable::class, 'active', 'stables', 'services', 'stables.show'],
        'clinics' => [Clinic::class, 'active', 'clinics', 'services', 'clinics.show'],
        'centers' => [Center::class, 'active', 'centers', 'services', 'centers.show'],
        'shops' => [Shop::class, 'active', 'shops', 'services', 'shops.show'],
        'events' => [Event::class, 'upcoming', 'events', 'events', null],
        'blog' => [CmsPost::class, 'published', 'blog', 'news', 'blog.show'],
        'videos' => [Video::class, 'active', 'videos', 'news', 'video-library.show'],
    ];

    /** The columns that can put an existing item live. */
    private const LIVE_COLUMNS = ['status', 'is_active', 'approval_status', 'published_at'];

    public static function listen(): void
    {
        foreach (self::SECTIONS as $section => [$model]) {
            Events::listen("eloquent.created: {$model}", fn (Model $item) => self::announce($section, $item, true));
            Events::listen("eloquent.updated: {$model}", fn (Model $item) => self::announce($section, $item, false));
        }
    }

    public static function enabled(string $section): bool
    {
        return self::settings()['sections'][$section] ?? false;
    }

    public static function dailyCap(): int
    {
        return self::settings()['daily_cap'];
    }

    /** Is this campaign's content still live, and its section still on? Checked again when it is sent. */
    public static function stillWanted(PushCampaign $campaign): bool
    {
        return self::enabled((string) $campaign->source) && self::live((string) $campaign->source, (int) $campaign->source_id);
    }

    public static function feature(string $section): ?string
    {
        return self::SECTIONS[$section][2] ?? null;
    }

    /** The line under the title, in the current language. */
    public static function body(string $section, Model $item): string
    {
        return match ($section) {
            'horses', 'tools' => trim($item->getAttribute('name').' · '.SiteSetting::formatCurrency((float) $item->getAttribute('price')), ' ·'),
            'transfers' => implode(__('api.push.route_arrow'), array_filter([
                $item->fromCity?->name ?? $item->fromRegion?->name ?? $item->fromCountry?->name,
                $item->toCity?->name ?? $item->toRegion?->name ?? $item->toCountry?->name,
            ])),
            'events', 'blog', 'videos' => (string) $item->getAttribute('title'),
            default => (string) $item->getAttribute('name'),
        };
    }

    /** Never throws: saving the post matters more than announcing it. */
    private static function announce(string $section, Model $item, bool $created): void
    {
        try {
            if (! self::enabled($section) || (! $created && ! $item->wasChanged(self::LIVE_COLUMNS))) {
                return;
            }

            // a blog post published for later is announced at that time
            $later = $item instanceof CmsPost && $item->status === 'published' && $item->published_at?->isFuture();

            if (! $later && ! self::live($section, $item->getKey())) {
                return;
            }

            [, , $feature, $topic, $route] = self::SECTIONS[$section];
            $texts = [];

            foreach (['en', 'ar'] as $locale) {
                $texts[$locale.'_title'] = __('api.push.new_'.$section, [], $locale);
                $texts[$locale.'_body'] = Locale::within($locale, fn () => Str::limit(self::body($section, $item), 200)) ?: null;
            }

            $key = ['source' => $section, 'source_id' => $item->getKey()];

            // no events: not an admin's change for the activity log, and the status is set here
            PushCampaign::withoutEvents(function () use ($key, $texts, $topic, $route, $feature, $item, $later) {
                PushCampaign::query()->where($key)->where('status', 'scheduled')->delete();
                PushCampaign::query()->firstOrCreate($key, $texts + [
                    'topic' => $topic,
                    'action_type' => $route ? MobileAnnouncement::ACTION_URL : MobileAnnouncement::ACTION_SCREEN,
                    'action_url' => $route ? route($route, $item->getAttribute('slug') ?? $item->getKey()) : null,
                    'action_screen' => $route ? null : $feature,
                    'status' => 'scheduled',
                    'scheduled_at' => $later ? $item->getAttribute('published_at') : now(),
                ]);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function live(string $section, int $id): bool
    {
        [$model, $scope] = self::SECTIONS[$section];

        return $model::query()->{$scope}()->whereKey($id)->exists();
    }

    /** @return array{sections: array<string, bool>, daily_cap: int} */
    public static function settings(): array
    {
        $value = SiteSetting::get('mobile.auto_push');
        $stored = is_string($value) && $value !== '' ? (array) json_decode($value, true) : [];
        $cap = (int) ($stored['daily_cap'] ?? self::DEFAULT_DAILY_CAP);

        return [
            'sections' => array_map(fn (string $section) => (bool) ($stored['sections'][$section] ?? false), array_combine(array_keys(self::SECTIONS), array_keys(self::SECTIONS))),
            'daily_cap' => $cap >= 1 && $cap <= self::MAX_DAILY_CAP ? $cap : self::DEFAULT_DAILY_CAP,
        ];
    }

    /** @param  array<string, mixed>  $state  the admin form's `auto_push` state */
    public static function save(array $state): void
    {
        SiteSetting::set('mobile.auto_push', json_encode([
            'sections' => array_map('boolval', (array) ($state['sections'] ?? [])),
            'daily_cap' => (int) ($state['daily_cap'] ?? self::DEFAULT_DAILY_CAP),
        ]), 'text', null, 'mobile_app');
    }
}
