<?php

namespace App\Models;

use App\Support\Games\RaceCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One run of the horse racing game (e.g. "Ramadan Race Challenge"), set up in admin → Games: when it
 * is open, the race and difficulty everyone rides, how often a customer may play, the leaderboard and
 * the prizes for the top ranks. Shown on a website page through the `game_campaign` CMS block, which
 * the app opens through admin → Mobile app → Pages.
 */
class GameCampaign extends Model
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public const PHASE_DRAFT = 'draft';

    public const PHASE_UPCOMING = 'upcoming';

    public const PHASE_LIVE = 'live';

    public const PHASE_ENDED = 'ended';

    protected $guarded = ['id', 'awarded_at', 'awarded_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'leaderboard_freeze_at' => 'datetime',
            'awarded_at' => 'datetime',
            'attempts_per_day' => 'integer',
            'attempts_total' => 'integer',
            'risk_threshold' => 'integer',
            'leaderboard_public' => 'boolean',
            'leaderboard_size' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return array_combine(self::STATUSES, array_map(fn ($s) => __('games.admin.statuses.'.$s), self::STATUSES));
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(GamePrize::class)->orderBy('rank');
    }

    /** Real races of the racing club the players choose from; none = the game's own course (`race_id`). */
    public function races(): HasMany
    {
        return $this->hasMany(GameRace::class)->orderBy('race_date')->orderBy('post_time')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(GameAttempt::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(GameAward::class)->orderBy('rank');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function phase(): string
    {
        return match (true) {
            $this->status !== 'published' => self::PHASE_DRAFT,
            $this->starts_at->isFuture() => self::PHASE_UPCOMING,
            $this->ends_at->isPast() => self::PHASE_ENDED,
            default => self::PHASE_LIVE,
        };
    }

    public function isLive(): bool
    {
        return $this->phase() === self::PHASE_LIVE;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    /** The public board stops moving; later attempts still count for the prizes. */
    public function isFrozen(): bool
    {
        return $this->leaderboard_freeze_at !== null && $this->leaderboard_freeze_at->isPast();
    }

    public function isAwarded(): bool
    {
        return $this->awarded_at !== null;
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function localized(string $field): ?string
    {
        return (app()->getLocale() === 'ar' ? $this->{'ar_'.$field} : $this->{'en_'.$field}) ?: null;
    }

    public function raceName(): string
    {
        $race = RaceCatalog::race($this->race_id);

        return $race[app()->getLocale() === 'ar' ? 'ar' : 'en'];
    }

    public function raceDistance(): int
    {
        return RaceCatalog::race($this->race_id)['distance'];
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    public function sponsorLogoUrl(): ?string
    {
        return $this->sponsor_logo ? Storage::disk('public')->url($this->sponsor_logo) : null;
    }

    /** The game itself, for this campaign, in a language (the game reads both from its query). */
    public function gameUrl(?string $locale = null): string
    {
        return url(RaceCatalog::GAME_PATH).'?'.http_build_query(['campaign' => $this->slug, 'lang' => $locale ?? app()->getLocale()]);
    }
}
