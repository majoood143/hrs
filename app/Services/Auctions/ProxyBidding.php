<?php

namespace App\Services\Auctions;

/**
 * The bidding rules, without the database (so every case is a plain unit test). All amounts in baisa.
 *
 * Only the leader's maximum matters: anyone else's maximum is already below the price, or they would
 * be leading. A new bid either beats the leader's maximum (and the price becomes one step above it,
 * never above the new maximum) or the leader's proxy answers one step above the new bid (never above
 * the leader's maximum). Equal maximums: the earlier one, the leader, keeps the lot. When the leader's
 * maximum covers a hidden reserve the price jumps to the reserve.
 */
final class ProxyBidding
{
    public const BID = 'bid';

    public const MAX = 'max';

    public const AUTO = 'auto';

    /**
     * @param  array{price: ?int, leader: ?int, leader_max: ?int}  $state
     */
    public static function minimumNext(array $state, int $starting, BidIncrements $steps): int
    {
        return $state['leader'] === null ? $starting : $state['price'] + $steps->after($state['price']);
    }

    /**
     * Applies one bid the caller has already checked (`$amount` at least the minimum, unless the
     * bidder leads). Returns the new state and the rows to write, in order.
     *
     * @param  array{price: ?int, leader: ?int, leader_max: ?int}  $state
     * @return array{price: int, leader: int, leader_max: int, rows: array<int, array{bidder: int, kind: string, amount: int, max: int}>}
     */
    public static function apply(array $state, int $bidder, int $amount, ?int $max, ?int $reserve, BidIncrements $steps): array
    {
        $max = max($amount, $max ?? $amount);
        $rows = [];

        if ($state['leader'] === null) {
            [$price, $leader, $leaderMax] = [$amount, $bidder, $max];
            $rows[] = self::row($bidder, self::BID, $amount, $max);
        } elseif ($state['leader'] === $bidder) {
            // the leader cannot outbid themselves: a bid only raises their maximum
            [$price, $leader, $leaderMax] = [$state['price'], $bidder, max($state['leader_max'], $max)];
            $rows[] = self::row($bidder, self::MAX, $price, $leaderMax);
        } elseif ($max > $state['leader_max']) {
            $old = $state['leader'];
            $oldMax = $state['leader_max'];
            $price = max($amount, min($max, $oldMax + $steps->after($oldMax)));

            $rows[] = self::row($bidder, self::BID, $amount, $max);
            // the old leader's proxy answered up to its maximum (shown only when that was above this bid)
            if ($oldMax > $amount) {
                $rows[] = self::row($old, self::AUTO, $oldMax, $oldMax);
            }
            if ($price > $amount) {
                $rows[] = self::row($bidder, self::AUTO, $price, $max);
            }
            [$leader, $leaderMax] = [$bidder, $max];
        } else {
            $rows[] = self::row($bidder, self::BID, $amount, $max);
            if ($max > $amount) {
                $rows[] = self::row($bidder, self::AUTO, $max, $max);
            }
            [$leader, $leaderMax] = [$state['leader'], $state['leader_max']];
            $price = min($leaderMax, $max + $steps->after($max));
            $rows[] = self::row($leader, self::AUTO, $price, $leaderMax);
        }

        if ($reserve !== null && $price < $reserve && $leaderMax >= $reserve) {
            $price = $reserve;
            $rows[] = self::row($leader, self::AUTO, $price, $leaderMax);
        }

        return ['price' => $price, 'leader' => $leader, 'leader_max' => $leaderMax, 'rows' => $rows];
    }

    /** @return array{bidder: int, kind: string, amount: int, max: int} */
    private static function row(int $bidder, string $kind, int $amount, int $max): array
    {
        return ['bidder' => $bidder, 'kind' => $kind, 'amount' => $amount, 'max' => $max];
    }
}
