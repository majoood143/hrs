<?php

namespace App\Services\Games;

use App\Models\Customer;
use App\Models\GameAttempt;
use App\Models\GameCampaign;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Who leads a campaign. Each customer's best eligible race counts: the most points (GameScore: the
 * place, times the outsider bonus on a real race), then the better place, the faster time, and
 * whoever got there first. Blocked players and races held for review
 * (or thrown out) do not rank. Public boards show a first name and a masked phone only.
 */
class LeaderboardService
{
    /** Seconds a public board is cached; finishing a race clears it at once. */
    public const CACHE_SECONDS = 15;

    /**
     * Every customer's best eligible race, one row each, unordered.
     *
     * @param  \DateTimeInterface|null  $cutoff  only races finished by then (a frozen board)
     */
    public function bestAttempts(GameCampaign $campaign, ?\DateTimeInterface $cutoff = null): Builder
    {
        $ranked = DB::table('game_attempts as a')
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->where('a.game_campaign_id', $campaign->id)
            ->where('a.status', GameAttempt::STATUS_COMPLETED)
            ->where(fn ($q) => $q->whereNull('a.review_status')->orWhere('a.review_status', GameAttempt::REVIEW_CLEARED))
            ->whereNull('c.games_blocked_at')
            ->when($cutoff, fn ($q) => $q->where('a.finished_at', '<=', $cutoff))
            ->select(['a.id as attempt_id', 'a.customer_id', 'a.score', 'a.position', 'a.race_time_ms', 'a.finished_at', 'c.phone', 'c.name'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY a.customer_id ORDER BY a.score DESC, a.position ASC, a.race_time_ms ASC, a.finished_at ASC, a.id ASC) as customer_rank');

        return DB::query()->fromSub($ranked, 'best')->where('customer_rank', 1);
    }

    /**
     * The top of the board, in order.
     *
     * @return list<array{rank: int, attempt_id: int, customer_id: int, phone: string, name: ?string, score: int, position: int, race_time_ms: int}>
     */
    public function top(GameCampaign $campaign, int $limit, ?\DateTimeInterface $cutoff = null): array
    {
        return $this->ordered($this->bestAttempts($campaign, $cutoff))->limit($limit)->get()->values()->map(fn ($row, $i) => [
            'rank' => $i + 1,
            'attempt_id' => (int) $row->attempt_id,
            'customer_id' => (int) $row->customer_id,
            'phone' => (string) $row->phone,
            'name' => $row->name,
            'score' => (int) $row->score,
            'position' => (int) $row->position,
            'race_time_ms' => (int) $row->race_time_ms,
        ])->all();
    }

    /**
     * The public board: names masked, frozen when the campaign says so, cached briefly.
     *
     * @return array{rows: list<array{rank: int, player: string, score: int, position: int, time: string}>, players: int, frozen: bool}
     */
    public function publicBoard(GameCampaign $campaign): array
    {
        return Cache::remember($this->cacheKey($campaign), self::CACHE_SECONDS, function () use ($campaign) {
            $cutoff = $campaign->isFrozen() ? $campaign->leaderboard_freeze_at : null;

            return [
                'rows' => array_map(fn (array $row) => [
                    'rank' => $row['rank'],
                    'player' => self::publicName($row['name'], $row['phone']),
                    'score' => $row['score'],
                    'position' => $row['position'],
                    'time' => GameAttempt::formatTime($row['race_time_ms']),
                ], $this->top($campaign, max(1, $campaign->leaderboard_size), $cutoff)),
                'players' => $this->bestAttempts($campaign, $cutoff)->count(),
                'frozen' => $cutoff !== null,
            ];
        });
    }

    /**
     * Where a customer stands (live, never frozen), or null before they have an eligible race.
     *
     * @return array{rank: int, of: int, attempt_id: int, score: int, position: int, race_time_ms: int, time: string}|null
     */
    public function standing(GameCampaign $campaign, Customer $customer): ?array
    {
        $best = $this->bestAttempts($campaign);
        $mine = (clone $best)->where('customer_id', $customer->id)->first();

        if (! $mine) {
            return null;
        }

        // the board's own order (ordered()), as "who is ahead of me"
        $ahead = (clone $best)->where(function ($q) use ($mine) {
            $q->where('score', '>', $mine->score)
                ->orWhere(fn ($q) => $q->where('score', $mine->score)->where('position', '<', $mine->position))
                ->orWhere(fn ($q) => $q->where('score', $mine->score)->where('position', $mine->position)->where('race_time_ms', '<', $mine->race_time_ms))
                ->orWhere(fn ($q) => $q->where('score', $mine->score)->where('position', $mine->position)->where('race_time_ms', $mine->race_time_ms)->where('finished_at', '<', $mine->finished_at))
                ->orWhere(fn ($q) => $q->where('score', $mine->score)->where('position', $mine->position)->where('race_time_ms', $mine->race_time_ms)->where('finished_at', $mine->finished_at)->where('attempt_id', '<', $mine->attempt_id));
        })->count();

        return [
            'rank' => $ahead + 1,
            'of' => (clone $best)->count(),
            'attempt_id' => (int) $mine->attempt_id,
            'score' => (int) $mine->score,
            'position' => (int) $mine->position,
            'race_time_ms' => (int) $mine->race_time_ms,
            'time' => GameAttempt::formatTime((int) $mine->race_time_ms),
        ];
    }

    public function forget(GameCampaign $campaign): void
    {
        Cache::forget($this->cacheKey($campaign));
    }

    /** "Salim · ••••4567": the first name (when they gave one) and enough of the phone to tell players apart. */
    public static function publicName(?string $name, string $phone): string
    {
        $first = trim(explode(' ', trim((string) $name))[0] ?? '');

        return ($first !== '' ? $first.' · ' : '').'••••'.substr($phone, -4);
    }

    private function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('score')->orderBy('position')->orderBy('race_time_ms')->orderBy('finished_at')->orderBy('attempt_id');
    }

    private function cacheKey(GameCampaign $campaign): string
    {
        return "games.leaderboard.{$campaign->id}";
    }
}
