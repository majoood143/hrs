<?php

namespace App\Models;

use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileAutoPush;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileScreens;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A push notification campaign (admin → Mobile app → Notifications): a bilingual message, what a tap
 * opens (an app screen or a link, like the announcements' button), who gets it, and when. Sent by
 * App\Jobs\SendPushCampaign; each phone it reached gets a PushDelivery (the app's inbox, opens).
 */
class PushCampaign extends Model
{
    use LogsActivity;

    public const AUDIENCES = ['everyone', 'customers', 'owners', 'guests', 'phones', 'stable_customers', 'stable_owners', 'segment'];

    /** The Android channels the app makes (src/features/push/push.tsx): bookings = high importance. */
    public const CHANNELS = ['default', 'bookings'];

    /** Quiet hours in Oman: a campaign that would go out from QUIET_FROM:00 waits until QUIET_UNTIL:00. */
    public const QUIET_FROM = 22;

    public const QUIET_UNTIL = 7;

    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent'];

    /** A phone gets at most this many campaigns in 24 hours (booking alerts and automatic ones are not counted: MobileAutoPush::dailyCap()). */
    public const DAILY_CAP = 2;

    protected $fillable = [
        'source', 'source_id', 'en_title', 'ar_title', 'en_body', 'ar_body', 'image_path',
        'action_type', 'action_screen', 'action_url',
        'audience', 'target_phones', 'target_stables', 'crm_segment_id', 'topic', 'platforms', 'locales', 'min_version', 'max_version', 'active_days',
        'priority', 'ttl_hours', 'sound', 'channel', 'quiet_hours', 'ignore_cap', 'spread_minutes',
        'status', 'scheduled_at', 'sent_at', 'recipients', 'accepted', 'opened', 'created_by',
    ];

    protected $casts = [
        'platforms' => 'array',
        'locales' => 'array',
        'active_days' => 'integer',
        'target_phones' => 'array',
        'target_stables' => 'array',
        'ttl_hours' => 'integer',
        'sound' => 'boolean',
        'quiet_hours' => 'boolean',
        'ignore_cap' => 'boolean',
        'spread_minutes' => 'integer',
        'pending_devices' => 'array',
        'next_batch_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients' => 'integer',
        'accepted' => 'integer',
        'opened' => 'integer',
    ];

    protected static function booted(): void
    {
        // saved in the admin: a time to send = scheduled, none = draft (never once it is going out)
        static::saving(function (PushCampaign $campaign) {
            if (in_array($campaign->status, [null, 'draft', 'scheduled'], true)) {
                $campaign->status = $campaign->scheduled_at ? 'scheduled' : 'draft';
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['en_title', 'ar_title', 'action_type', 'action_screen', 'action_url', 'audience', 'topic',
                'platforms', 'locales', 'min_version', 'max_version', 'active_days', 'status', 'scheduled_at',
                'target_phones', 'target_stables', 'crm_segment_id', 'priority', 'ttl_hours', 'sound', 'channel', 'quiet_hours', 'ignore_cap', 'spread_minutes'])
            ->logOnlyDirty()
            ->useLogName('mobile_app');
    }

    /** The audience "segment": its customers' signed-in phones. */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(CrmSegment::class, 'crm_segment_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        // scheduled ones whose time came, and the next batch of one spread over some minutes
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $s) => $s->where('status', 'scheduled')->where('scheduled_at', '<=', now()))
            ->orWhere(fn (Builder $s) => $s->where('status', 'sending')->where('next_batch_at', '<=', now())));
    }

    public function editable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /** The phones it goes to now: the audience, the topic, the filters, the daily cap, and the tapped screen's feature. */
    public function devices(): Collection
    {
        $feature = $this->source
            ? MobileAutoPush::feature($this->source)
            : ($this->action_type === MobileAnnouncement::ACTION_SCREEN ? MobileScreens::feature((string) $this->action_screen) : null);
        $automatic = $this->source !== null;
        // a phone with a saved search that matches already got its own push about this post
        $told = $automatic ? PushSavedSearch::deviceIdsFor($this->source, (int) $this->source_id) : [];

        return MobileDevice::query()
            ->where('enabled', true)
            ->when($this->audience === 'customers', fn (Builder $q) => $q->whereNotNull('customer_token_id'))
            ->when($this->audience === 'owners', fn (Builder $q) => $q->whereNotNull('owner_token_id'))
            ->when($this->audience === 'guests', fn (Builder $q) => $q->whereNull('customer_token_id')->whereNull('owner_token_id'))
            ->when($this->audience === 'phones', fn (Builder $q) => $q->signedInWithPhones($this->target_phones ?? []))
            ->when($this->audience === 'stable_customers', fn (Builder $q) => $q->whereNotNull('customer_token_id')
                ->whereIn('customer_id', StableBooking::query()->whereIn('stable_id', $this->target_stables ?? [])->whereNotNull('customer_id')->select('customer_id')))
            ->when($this->audience === 'stable_owners', fn (Builder $q) => $q->whereNotNull('owner_token_id')
                ->whereIn('user_id', DB::table('stable_user')->whereIn('stable_id', $this->target_stables ?? [])->select('user_id')))
            // a deleted segment reaches nobody (never everyone)
            ->when($this->audience === 'segment', fn (Builder $q) => $this->segment
                ? $q->whereNotNull('customer_token_id')->whereIn('customer_id', $this->segment->customers()->select('customers.id'))
                : $q->whereRaw('1 = 0'))
            ->where(fn (Builder $q) => $q->whereNull('topics')->orWhereJsonContains('topics', $this->topic))
            ->when(! empty($this->platforms), fn (Builder $q) => $q->whereIn('platform', $this->platforms))
            ->when(! empty($this->locales), fn (Builder $q) => $q->whereIn('locale', $this->locales))
            ->when($this->active_days, fn (Builder $q) => $q->where('last_seen_at', '>=', now()->subDays($this->active_days)))
            ->when(! $this->ignore_cap, fn (Builder $q) => $q->has('deliveries', '<', $automatic ? MobileAutoPush::dailyCap() : self::DAILY_CAP, 'and', fn (Builder $q) => $q
                ->where('created_at', '>', now()->subDay())
                ->whereHas('campaign', fn (Builder $c) => $automatic ? $c->whereNotNull('source') : $c->whereNull('source'))))
            ->get()
            ->filter(function (MobileDevice $device) use ($feature, $told) {
                $client = new AppClient($device->platform, $device->app_version);

                return ! in_array($device->getKey(), $told, true) && $client->atLeast($this->min_version) && $client->atMost($this->max_version)
                    && ($feature === null || MobileFeatures::enabledFor($feature, $client));
            })
            ->values();
    }

    /** Expo's delivery fields for it (App\Services\Push\ExpoPush::send() $options). */
    public function pushOptions(): array
    {
        return array_filter([
            'priority' => $this->priority === 'high' ? 'high' : null,
            'ttl' => $this->ttl_hours ? $this->ttl_hours * 3600 : null,
            'channelId' => in_array($this->channel, self::CHANNELS, true) ? $this->channel : null,
        ]) + ($this->sound === false ? ['sound' => null] : []);
    }

    /** With quiet hours on and Oman's clock inside them: when it may go out (UTC); else null. */
    public function quietUntil(): ?CarbonImmutable
    {
        $local = CarbonImmutable::now(BookingSlot::timezone());

        if (! $this->quiet_hours || ($local->hour < self::QUIET_FROM && $local->hour >= self::QUIET_UNTIL)) {
            return null;
        }

        $resume = $local->setTime(self::QUIET_UNTIL, 0);

        return ($resume->lessThanOrEqualTo($local) ? $resume->addDay() : $resume)->utc();
    }

    /** What the app gets with it: the campaign (for "opened") and what a tap opens. */
    public function payload(): array
    {
        return array_filter([
            'campaign' => $this->getKey(),
            'screen' => $this->action_type === MobileAnnouncement::ACTION_SCREEN ? $this->action_screen : null,
            'url' => $this->action_type === MobileAnnouncement::ACTION_URL ? $this->action_url : null,
        ]);
    }

    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $this->getAttribute($locale.'_'.$field) ?: $this->getAttribute('en_'.$field);

        return filled($value) ? (string) $value : null;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
