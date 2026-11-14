<?php

namespace App\Models;

use App\Support\MobileApp\AppClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Something a person did in the app (POST /api/v1/events) or that the server saw happen for them
 * (a booking confirmed, a package bought…): the CRM's timeline and segments. Kept 12 months.
 */
class AppEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const KEEP_MONTHS = 12;

    /** What the app may send; anything else is dropped. Keep in step with the app's `features/tracking/events.ts`. */
    public const CLIENT = [
        'first_open', 'app_launched', 'app_updated', 'screen_viewed', 'language_changed',
        'onboarding_viewed', 'onboarding_completed', 'onboarding_skipped',
        'signed_in', 'signed_out', 'push_opened',
        'listing_viewed', 'filter_applied', 'contact_tapped', 'shared',
        'saved_search_created', 'ad_clicked', 'video_played', 'booking_started',
    ];

    /** Recorded by the server only (the app cannot claim them). */
    public const SERVER = [
        'signed_up', 'profile_updated', 'post_submitted', 'booking_confirmed', 'booking_cancelled', 'package_activated',
    ];

    public const MAX_PROPERTIES = 20;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['properties' => 'array', 'occurred_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('occurred_at', '<', now()->subMonths(self::KEEP_MONTHS));
    }

    /** The install id the app sends in `X-Install-Id`, when it looks like one. */
    public static function installId(Request $request): ?string
    {
        $id = (string) $request->header('X-Install-Id');

        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) ? $id : null;
    }

    /**
     * Records a server-side event for this customer (and the app install making the request, if any).
     *
     * @param  array<string, scalar|null>  $properties
     */
    public static function record(string $name, ?Customer $customer, array $properties = [], ?Request $request = null): void
    {
        $request ??= request();
        $install = self::installId($request);

        if ($customer === null && $install === null) {
            return; // a website visitor: nobody to put it on
        }

        $client = AppClient::fromRequest($request);

        static::query()->create([
            'install_id' => $install,
            'customer_id' => $customer?->getKey(),
            'name' => $name,
            'properties' => self::cleanProperties($properties) ?: null,
            'platform' => $client->platform,
            'app_version' => $client->version,
            'locale' => app()->getLocale(),
            'occurred_at' => now(),
        ]);
    }

    /** On sign-in: what this install did before (anonymously) becomes the customer's. */
    public static function claim(?string $installId, Customer $customer): void
    {
        if ($installId !== null) {
            static::query()->where('install_id', $installId)->whereNull('customer_id')->update(['customer_id' => $customer->getKey()]);
        }
    }

    /**
     * At most MAX_PROPERTIES scalar values, keys up to 40 characters, strings cut at 255.
     *
     * @return array<string, scalar|null>
     */
    public static function cleanProperties(mixed $properties): array
    {
        if (! is_array($properties)) {
            return [];
        }

        return collect($properties)
            ->filter(fn ($value, $key) => is_string($key) && strlen($key) <= 40 && (is_scalar($value) || $value === null))
            ->map(fn ($value) => is_string($value) ? mb_substr($value, 0, 255) : $value)
            ->take(self::MAX_PROPERTIES)
            ->all();
    }
}
