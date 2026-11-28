<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One race in a campaign. It counts against the customer's limits as soon as it starts, so leaving
 * a bad race and starting again does not give a free retry. The game reports the finish; the server
 * measures the time between the start and the finish calls and scores how believable the result is
 * (Services\Games\RiskScorer). A high score waits for an admin before it can rank.
 */
class GameAttempt extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const REVIEW_FLAGGED = 'flagged';

    public const REVIEW_CLEARED = 'cleared';

    public const REVIEW_INVALIDATED = 'invalidated';

    /** An attempt nobody finished within this many minutes is abandoned (the longest race is about 2.5 min). */
    public const ABANDON_AFTER_MINUTES = 20;

    protected $guarded = ['id'];

    /** Milliseconds kept on the start and finish times: the server's own timing uses them. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'field_size' => 'integer',
            'race_time_ms' => 'integer',
            'top_speed_kmh' => 'decimal:1',
            'wall_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'risk_score' => 'integer',
            'score' => 'integer',
            'horse_rating' => 'integer',
            'horse_rating_rank' => 'integer',
            'flags' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GameCampaign::class, 'game_campaign_id');
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(GameRace::class, 'game_race_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Finished and not held back or thrown out by a review: counts for the leaderboard and prizes. */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->where(fn (Builder $q) => $q->whereNull('review_status')->orWhere('review_status', self::REVIEW_CLEARED));
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_STARTED;
    }

    /** 65370 → "1:05.37", the way the game shows times. */
    public static function formatTime(?int $ms): string
    {
        if ($ms === null) {
            return '—';
        }

        $minutes = intdiv($ms, 60000);

        return $minutes.':'.str_pad(number_format(($ms - $minutes * 60000) / 1000, 2, '.', ''), 5, '0', STR_PAD_LEFT);
    }
}
