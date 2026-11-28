<?php

namespace App\Services\Games;

use App\Models\Customer;
use App\Models\GameAttempt;
use App\Models\GameCampaign;
use App\Models\GameRace;
use App\Support\Games\RaceCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Runs the races of a campaign: starts one when the campaign is open and the customer has races left
 * (starting uses one up, so quitting a bad race does not give a free retry), and takes the finish the
 * game reports, timed by the server and scored by RiskScorer.
 */
class GameAttemptService
{
    public function __construct(
        private readonly RiskScorer $risk,
        private readonly LeaderboardService $leaderboard,
    ) {}

    /**
     * Races the customer may still start: today's (Muscat's day) and in total, null meaning unlimited.
     *
     * @return array{today: ?int, total: ?int, can_play: bool}
     */
    public function remaining(GameCampaign $campaign, Customer $customer): array
    {
        $attempts = GameAttempt::query()->where('game_campaign_id', $campaign->id)->where('customer_id', $customer->id);

        $today = $campaign->attempts_per_day === null
            ? null
            : max(0, $campaign->attempts_per_day - (clone $attempts)->where('started_at', '>=', now(RaceCatalog::TIMEZONE)->startOfDay()->utc())->count());

        $total = $campaign->attempts_total === null
            ? null
            : max(0, $campaign->attempts_total - (clone $attempts)->count());

        return ['today' => $today, 'total' => $total, 'can_play' => $today !== 0 && $total !== 0];
    }

    /**
     * @param  GameRace|null  $race  one of the campaign's real races (required when it has any)
     * @param  string|null  $horseNo  the horse the player rides in it (its saddle-cloth number)
     */
    public function start(GameCampaign $campaign, Customer $customer, ?string $horseStyle, ?string $ip, ?string $userAgent, ?GameRace $race = null, ?string $horseNo = null): GameAttempt
    {
        if (! $campaign->isLive()) {
            throw new GameException($campaign->hasEnded() ? 'campaign_ended' : 'campaign_not_open');
        }

        if ($customer->games_blocked_at !== null) {
            throw new GameException('player_blocked', status: 403);
        }

        $runner = $race?->runner((string) $horseNo);
        if ($campaign->races()->exists() && (! $race || $race->game_campaign_id !== $campaign->id || ! $runner)) {
            throw new GameException('choose_race');
        }

        return DB::transaction(function () use ($campaign, $customer, $horseStyle, $ip, $userAgent, $race, $runner) {
            // one race at a time: serialise this customer's starts
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            GameAttempt::query()
                ->where('game_campaign_id', $campaign->id)
                ->where('customer_id', $customer->id)
                ->where('status', GameAttempt::STATUS_STARTED)
                ->update(['status' => GameAttempt::STATUS_ABANDONED, 'updated_at' => now()]);

            if (! $this->remaining($campaign, $customer)['can_play']) {
                throw new GameException('no_attempts_left');
            }

            return GameAttempt::query()->create([
                'uuid' => (string) Str::uuid(),
                'game_campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'status' => GameAttempt::STATUS_STARTED,
                'horse_style' => in_array($horseStyle, RaceCatalog::HORSE_STYLES, true) ? $horseStyle : null,
                'game_race_id' => $race?->id,
                'horse_no' => $runner['no'] ?? null,
                'horse_rating' => $runner['rating'] ?? null,
                'horse_rating_rank' => $runner ? $race->ratingRank($runner['no']) : null,
                'started_at' => Carbon::now(),
                'flags' => [],
                'ip' => $ip,
                'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            ]);
        });
    }

    /**
     * The game's finish for an attempt. Sent twice (a retry after a dropped connection), the second
     * answer is the first one's result; the race is never scored twice.
     *
     * @param  array{position: int, field_size: int, race_time_ms: int, top_speed_kmh: float, webdriver?: bool}  $result
     * @return array<string, mixed>
     */
    public function finish(GameAttempt $attempt, array $result): array
    {
        $attempt = DB::transaction(function () use ($attempt, $result) {
            /** @var GameAttempt $attempt */
            $attempt = GameAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->status === GameAttempt::STATUS_COMPLETED) {
                return $attempt;
            }

            if (! $attempt->isOpen()) {
                throw new GameException('attempt_closed');
            }

            $now = Carbon::now();

            if ($attempt->started_at->lt($now->copy()->subMinutes(GameAttempt::ABANDON_AFTER_MINUTES))) {
                $attempt->update(['status' => GameAttempt::STATUS_ABANDONED]);

                throw new GameException('attempt_expired');
            }

            $campaign = $attempt->campaign;
            $wallMs = (int) round($now->getPreciseTimestamp(3) - $attempt->started_at->getPreciseTimestamp(3));
            $race = $attempt->race;
            $flags = $this->risk->flags($race?->distance ?? RaceCatalog::race($campaign->race_id)['distance'], $result, $wallMs);
            $score = $this->risk->score($flags);

            $attempt->fill([
                'status' => GameAttempt::STATUS_COMPLETED,
                'position' => $result['position'],
                'field_size' => $result['field_size'],
                'race_time_ms' => $result['race_time_ms'],
                'top_speed_kmh' => round($result['top_speed_kmh'], 1),
                'score' => GameScore::points($result['position'], $attempt->horse_rating_rank, $result['field_size']),
                'wall_ms' => $wallMs,
                'finished_at' => $now,
                'flags' => $flags,
                'risk_score' => $score,
                'review_status' => $score >= $campaign->risk_threshold ? GameAttempt::REVIEW_FLAGGED : null,
            ])->save();

            return $attempt;
        });

        $this->leaderboard->forget($attempt->campaign);

        return $this->result($attempt);
    }

    /** @return array<string, mixed> the finished race and where the customer now stands */
    public function result(GameAttempt $attempt): array
    {
        $standing = $this->leaderboard->standing($attempt->campaign, $attempt->customer);

        $race = $attempt->race;
        $real = null;

        // a race already run: how the player did against the real result
        if ($race?->has_result) {
            $winner = collect($race->runners)->firstWhere('result', 1);
            $mine = $race->runner((string) $attempt->horse_no);
            $real = [
                'horse_position' => $mine['result'] ?? null,
                'winner' => GameRace::text($winner['name'] ?? null),
                'winning_time' => $race->winning_time_ms ? GameAttempt::formatTime($race->winning_time_ms) : null,
            ];
        }

        return [
            'position' => $attempt->position,
            'score' => $attempt->score,
            'real' => $real,
            'time' => GameAttempt::formatTime($attempt->race_time_ms),
            'under_review' => $attempt->review_status === GameAttempt::REVIEW_FLAGGED,
            'is_best' => $standing !== null && $standing['attempt_id'] === $attempt->id,
            'standing' => $standing,
            'remaining' => $this->remaining($attempt->campaign, $attempt->customer),
        ];
    }
}
