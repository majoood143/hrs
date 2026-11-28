<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A real race (the racing club's) a campaign offers, with a copy of its declared field taken when the
 * admin added or refreshed it (Services\Games\RealRaces). Each runner in `runners`:
 * {no, gate, horse_id, name{en,ar}, age, colour{en,ar}, sex{en,ar}, record{starts,first,second,third},
 *  owner{en,ar}, trainer{en,ar}, jockey{en,ar}, weight, rating, silks, result} (`result` = the official
 * finishing place once the race was run, never sent to players before they ride it). A runner typed in
 * by hand may also carry `silks_upload` (a picture on the public disk).
 *
 * `source` is `club` (read from the racing club, `source_race_id`) or `manual` (typed in by an admin).
 * On a club race, `locked_nos` are the runners changed or added by hand and `removed_nos` the ones taken
 * out by hand: "Refresh field" leaves both as the admin set them.
 */
class GameRace extends Model
{
    public const SOURCE_CLUB = 'club';

    public const SOURCE_MANUAL = 'manual';

    /** The game's starting stalls hold 16 horses. */
    public const MAX_RUNNERS = 16;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'race_date' => 'date',
            'runners' => 'array',
            'locked_nos' => 'array',
            'removed_nos' => 'array',
            'source_race_id' => 'integer',
            'distance' => 'integer',
            'winning_time_ms' => 'integer',
            'has_result' => 'boolean',
            'refreshed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GameCampaign::class, 'game_campaign_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(GameAttempt::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function fieldSize(): int
    {
        return count($this->runners);
    }

    /** @return array<string, mixed>|null */
    public function runner(string $no): ?array
    {
        foreach ($this->runners as $runner) {
            if ((string) $runner['no'] === $no) {
                return $runner;
            }
        }

        return null;
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    /** 1 = the best-rated horse of the field; an unrated horse counts below every rated one. */
    public function ratingRank(string $no): int
    {
        return self::rankIn($this->runners, $no);
    }

    /**
     * A horse's rating rank in a list of runners (also a form's runners while they are typed).
     *
     * @param  array<int, array<string, mixed>>  $runners
     */
    public static function rankIn(array $runners, string $no): int
    {
        $rating = fn (array $r) => is_numeric($r['rating'] ?? null) ? (int) $r['rating'] : -1;
        $mine = -1;
        foreach ($runners as $r) {
            if ((string) ($r['no'] ?? '') === $no) {
                $mine = $rating($r);
            }
        }

        return 1 + count(array_filter($runners, fn (array $r) => (string) ($r['no'] ?? '') !== $no && $rating($r) > $mine));
    }

    /** A runner's silks picture: the one uploaded by hand, else the racing club's (through the website). */
    public static function silksUrl(array $runner): ?string
    {
        if (! empty($runner['silks_upload'])) {
            return Storage::disk('public')->url($runner['silks_upload']);
        }

        return ! empty($runner['silks']) ? route('racing.image', ['p' => $runner['silks']]) : null;
    }

    /** "Al Saktoon (OM)" in the current language, or the English name. */
    public static function text(?array $pair): ?string
    {
        if (! $pair) {
            return null;
        }

        return (app()->getLocale() === 'ar' ? ($pair['ar'] ?? null) : null) ?: ($pair['en'] ?? null);
    }
}
