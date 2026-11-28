<?php

namespace App\Services\Auctions;

use App\Jobs\SendAuctionMessage;
use App\Models\AppEvent;
use App\Models\AuctionLot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closes lots whose time is up: sold to the leader when there is no reserve or it is met, else
 * unsold. Safe to run twice (the lot's lock and status are checked again). Run every minute by
 * `auctions:close-due`; a bid can never land after `ends_at`, so a late close changes nothing.
 */
class CloseLot
{
    public function __invoke(AuctionLot $lot, ?Carbon $now = null): AuctionLot
    {
        $now ??= now();

        return DB::transaction(function () use ($lot, $now) {
            $lot = AuctionLot::query()->whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            if ($lot->status !== 'ready' || $lot->ends_at === null || $now->lt($lot->ends_at)) {
                return $lot;
            }

            $leading = $lot->leadingBid;
            $sold = $leading !== null && $lot->reserveMet() !== false;

            $lot->forceFill([
                'status' => $sold ? 'sold' : 'unsold',
                'hammer_price' => $sold ? $lot->current_price : null,
                'winner_bidder_id' => $sold ? $leading->auction_bidder_id : null,
                'closed_at' => $now,
            ])->save();

            if ($sold) {
                app(Settlement::class)->invoice($lot);
                app(AuctionNotifier::class)->won($lot);
                AppEvent::record('lot_won', $leading->bidder?->customer, ['auction' => $lot->auction->slug, 'lot' => $lot->lot_number, 'amount' => (string) $lot->hammer_price]);
            }
            if ($lot->seller_customer_id) {
                SendAuctionMessage::dispatch($sold ? SendAuctionMessage::SELLER_SOLD : SendAuctionMessage::SELLER_UNSOLD, $lot->id)->afterCommit();
            }

            return $lot;
        });
    }

    /** @return int how many lots were closed */
    public function due(?Carbon $now = null): int
    {
        $now ??= now();

        return AuctionLot::query()
            ->where('status', 'ready')
            ->where('ends_at', '<=', $now)
            ->whereHas('auction', fn ($q) => $q->where('status', 'published'))
            ->get()
            ->filter(fn (AuctionLot $lot) => $this($lot, $now)->status !== 'ready')
            ->count();
    }
}
