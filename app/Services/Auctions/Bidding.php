<?php

namespace App\Services\Auctions;

use App\Models\AuctionBid;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place bids are written: placing one (the website, the app, an admin on the phone) and
 * voiding one. Every change holds the lot's row lock, so two bids at the same moment are applied one
 * after the other and the second sees the first's price. Amounts in baisa.
 */
class Bidding
{
    /** A bid more than this many times the current (or starting) price is refused as a likely typo. */
    public const MAX_JUMP = 10;

    public function place(AuctionLot $lot, AuctionBidder $bidder, int $amount, ?int $max = null, ?Authenticatable $by = null, ?Carbon $now = null): AuctionLot
    {
        $now ??= now();

        return DB::transaction(function () use ($lot, $bidder, $amount, $max, $by, $now) {
            $lot = AuctionLot::query()->whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            if (! $lot->isOpenAt($now)) {
                throw new AuctionBidException($lot->auction->bidding_starts_at && $now->lt($lot->auction->bidding_starts_at) ? 'not_open_yet' : 'closed');
            }
            if ((int) $bidder->auction_id !== (int) $lot->auction_id || $bidder->status !== 'approved') {
                throw new AuctionBidException('not_approved');
            }
            if ($lot->seller_customer_id !== null && (int) $lot->seller_customer_id === (int) $bidder->customer_id) {
                throw new AuctionBidException('own_lot');
            }
            if ($max !== null && $max < $amount) {
                throw new AuctionBidException('max_below_amount');
            }

            $state = $lot->biddingState();
            $steps = $lot->auction->steps();
            $starting = Money::baisa($lot->starting_price);
            $minimum = $lot->minimumNextBid();

            if ($state['leader'] !== (int) $bidder->id && $amount < $minimum) {
                throw new AuctionBidException('too_low', ['minimum' => Money::omr($minimum)]);
            }
            if (max($amount, $max ?? 0) > max($state['price'] ?? 0, $starting) * self::MAX_JUMP) {
                throw new AuctionBidException('too_high');
            }

            $result = ProxyBidding::apply($state, (int) $bidder->id, $amount, $max, $this->reserve($lot), $steps);
            $leading = $this->write($lot, $result['rows'], $now, null, $by);

            // anti-sniping: a bid in the last N minutes moves this lot's close (only this lot's)
            $extended = $now->copy()->addMinutes($lot->auction->extension_minutes);
            if ($lot->ends_at->lt($extended)) {
                $lot->ends_at = $extended;
            }

            $lot = $this->settle($lot, $result['price'], $leading);

            if ($state['leader'] !== null && $state['leader'] !== $result['leader']) {
                app(AuctionNotifier::class)->outbid($state['leader'], $lot);
            }

            return $lot;
        });
    }

    /** Voids a placed bid (or maximum) and works the lot out again from the bids that remain. */
    public function void(AuctionBid $bid, Authenticatable $by, string $reason): AuctionLot
    {
        return DB::transaction(function () use ($bid, $by, $reason) {
            $lot = AuctionLot::query()->whereKey($bid->auction_lot_id)->lockForUpdate()->firstOrFail();
            $bid = $bid->fresh();

            if ($lot->status !== 'ready' || $bid->voided_at !== null || $bid->kind === ProxyBidding::AUTO) {
                throw new AuctionBidException('cannot_void');
            }

            $bid->update(['voided_at' => now(), 'voided_by_user_id' => $by->getAuthIdentifier(), 'void_reason' => $reason]);

            return $this->replay($lot, $by);
        });
    }

    /** Proxy rows follow from the placed ones, so they are dropped and the placed ones applied again in order. */
    private function replay(AuctionLot $lot, Authenticatable $by): AuctionLot
    {
        $lot->bids()->where('kind', ProxyBidding::AUTO)->delete();

        $steps = $lot->auction->steps();
        $starting = Money::baisa($lot->starting_price);
        $reserve = $this->reserve($lot);
        $state = ['price' => null, 'leader' => null, 'leader_max' => null];
        $leading = null;

        foreach ($lot->bids()->valid()->orderBy('id')->get() as $request) {
            $amount = Money::baisa($request->amount);
            $max = $request->max_amount !== null ? Money::baisa($request->max_amount) : null;

            if ($state['leader'] !== (int) $request->auction_bidder_id) {
                // without the voided bid the price is lower, so a bid can only be cut down to the new minimum or drop out
                $minimum = ProxyBidding::minimumNext($state, $starting, $steps);
                if (max($amount, $max ?? 0) < $minimum) {
                    $request->update(['voided_at' => now(), 'voided_by_user_id' => $by->getAuthIdentifier(), 'void_reason' => __('auctions.void_cascade')]);

                    continue;
                }
                $amount = max($amount, $minimum);
            }

            $result = ProxyBidding::apply($state, (int) $request->auction_bidder_id, $amount, $max, $reserve, $steps);
            $leading = $this->write($lot, $result['rows'], $request->created_at, $request, null);
            $state = ['price' => $result['price'], 'leader' => $result['leader'], 'leader_max' => $result['leader_max']];
        }

        return $this->settle($lot, $state['price'], $leading);
    }

    /**
     * Writes the rows of one bid (or rewrites the placed one, when replaying) and returns the last row of
     * the leader, which carries the price and the leader's maximum.
     *
     * @param  array<int, array{bidder: int, kind: string, amount: int, max: int}>  $rows
     */
    private function write(AuctionLot $lot, array $rows, Carbon $at, ?AuctionBid $request, ?Authenticatable $by): AuctionBid
    {
        $written = [];
        foreach ($rows as $row) {
            $attributes = ['kind' => $row['kind'], 'amount' => Money::omr($row['amount']), 'max_amount' => Money::omr($row['max'])];

            if ($row['kind'] !== ProxyBidding::AUTO && $request !== null) {
                $request->update($attributes);
                $written[] = $request;

                continue;
            }

            $written[] = $lot->bids()->create($attributes + [
                'auction_bidder_id' => $row['bidder'],
                'placed_by_user_id' => $row['kind'] === ProxyBidding::AUTO ? null : $by?->getAuthIdentifier(),
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $leader = end($rows)['bidder'];

        return collect($written)->last(fn (AuctionBid $bid) => (int) $bid->auction_bidder_id === $leader);
    }

    private function settle(AuctionLot $lot, ?int $price, ?AuctionBid $leading): AuctionLot
    {
        $visible = $lot->bids()->visible();

        $lot->forceFill([
            'current_price' => $price === null ? null : Money::omr($price),
            'leading_bid_id' => $leading?->id,
            'bids_count' => (clone $visible)->count(),
            'bidders_count' => (clone $visible)->distinct()->count('auction_bidder_id'),
        ])->save();

        return $lot;
    }

    private function reserve(AuctionLot $lot): ?int
    {
        return $lot->reserve_price === null ? null : Money::baisa($lot->reserve_price);
    }
}
