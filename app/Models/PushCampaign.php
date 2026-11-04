<?php

namespace App\Models;

use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileScreens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const AUDIENCES = ['everyone', 'customers', 'owners', 'guests'];

    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent'];

    /** A phone gets at most this many campaigns in 24 hours (booking alerts are not counted). */
    public const DAILY_CAP = 2;

    protected $fillable = [
        'en_title', 'ar_title', 'en_body', 'ar_body', 'image_path',
        'action_type', 'action_screen', 'action_url',
        'audience', 'topic', 'platforms', 'locales', 'min_version', 'max_version', 'active_days',
        'status', 'scheduled_at', 'sent_at', 'recipients', 'accepted', 'opened', 'created_by',
    ];

    protected $casts = [
        'platforms' => 'array',
        'locales' => 'array',
        'active_days' => 'integer',
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
                'platforms', 'locales', 'min_version', 'max_version', 'active_days', 'status', 'scheduled_at'])
            ->logOnlyDirty()
            ->useLogName('mobile_app');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')->where('scheduled_at', '<=', now());
    }

    public function editable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /** The phones it goes to now: the audience, the topic, the filters, the daily cap, and the tapped screen's feature. */
    public function devices(): Collection
    {
        $feature = $this->action_type === MobileAnnouncement::ACTION_SCREEN ? MobileScreens::feature((string) $this->action_screen) : null;

        return MobileDevice::query()
            ->when($this->audience === 'customers', fn (Builder $q) => $q->whereNotNull('customer_token_id'))
            ->when($this->audience === 'owners', fn (Builder $q) => $q->whereNotNull('owner_token_id'))
            ->when($this->audience === 'guests', fn (Builder $q) => $q->whereNull('customer_token_id')->whereNull('owner_token_id'))
            ->where(fn (Builder $q) => $q->whereNull('topics')->orWhereJsonContains('topics', $this->topic))
            ->when(! empty($this->platforms), fn (Builder $q) => $q->whereIn('platform', $this->platforms))
            ->when(! empty($this->locales), fn (Builder $q) => $q->whereIn('locale', $this->locales))
            ->when($this->active_days, fn (Builder $q) => $q->where('last_seen_at', '>=', now()->subDays($this->active_days)))
            ->has('deliveries', '<', self::DAILY_CAP, 'and', fn (Builder $q) => $q->where('created_at', '>', now()->subDay()))
            ->get()
            ->filter(function (MobileDevice $device) use ($feature) {
                $client = new AppClient($device->platform, $device->app_version);

                return $client->atLeast($this->min_version) && $client->atMost($this->max_version)
                    && ($feature === null || MobileFeatures::enabledFor($feature, $client));
            })
            ->values();
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
