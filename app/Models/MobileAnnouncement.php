<?php

namespace App\Models;

use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileScreens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A popup shown in the mobile app (admin → Mobile app → Announcements): bilingual title/text, an
 * optional image and button (a link or an app screen), who sees it (platforms, versions,
 * languages), when (start/end), and how often (once / daily / every launch; the app remembers).
 */
class MobileAnnouncement extends Model
{
    use LogsActivity;

    public const ACTION_NONE = 'none';

    public const ACTION_URL = 'url';

    public const ACTION_SCREEN = 'screen';

    public const FREQUENCIES = ['once', 'daily', 'every_launch'];

    protected $fillable = [
        'en_title', 'ar_title', 'en_body', 'ar_body', 'image_path',
        'action_type', 'action_url', 'action_screen', 'en_button_label', 'ar_button_label',
        'platforms', 'locales', 'min_version', 'max_version', 'starts_at', 'ends_at',
        'frequency', 'dismissible', 'priority', 'is_active',
    ];

    protected $casts = [
        'platforms' => 'array',
        'locales' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'dismissible' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['en_title', 'ar_title', 'action_type', 'action_url', 'action_screen', 'platforms', 'locales',
                'min_version', 'max_version', 'starts_at', 'ends_at', 'frequency', 'dismissible', 'priority', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('mobile_app');
    }

    /** Switched on and within its dates now. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** live | scheduled | ended | off, for the admin list. */
    public function status(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'ended',
            $this->starts_at !== null && $this->starts_at->gt(now()) => 'scheduled',
            default => 'live',
        };
    }

    /** For this app, in this language, with its button's feature on. */
    public function shownTo(AppClient $client, string $locale): bool
    {
        if (! empty($this->locales) && ! in_array($locale, $this->locales, true)) {
            return false;
        }

        if (! $client->onPlatform($this->platforms) || ! $client->atLeast($this->min_version) || ! $client->atMost($this->max_version)) {
            return false;
        }

        $feature = $this->action_type === self::ACTION_SCREEN ? MobileScreens::feature((string) $this->action_screen) : null;

        return $feature === null || MobileFeatures::enabledFor($feature, $client);
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
