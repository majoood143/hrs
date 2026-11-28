<?php

namespace App\Services\Auctions;

use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;

/**
 * The rules the website's bid board and the API share; each formats them its own way (the site as
 * text, the API as Money objects). Never the reserve amount or anyone else's maximum.
 */
class LotState
{
    /** leading, outbid, won, lost, or null (not registered or no bid on this lot) */
    public static function you(AuctionLot $lot, ?AuctionBidder $bidder): ?string
    {
        if (! $bidder) {
            return null;
        }

        $id = (int) $bidder->id;
        $leader = $lot->leadingBid ? (int) $lot->leadingBid->auction_bidder_id : null;

        return match (true) {
            $lot->status === 'sold' && (int) $lot->winner_bidder_id === $id => 'won',
            ! $lot->bids()->valid()->where('auction_bidder_id', $id)->exists() => null,
            in_array($lot->status, ['sold', 'unsold'], true) => 'lost',
            $leader === $id => 'leading',
            default => 'outbid',
        };
    }

    /** The price anyone may see: the hammer once sold (unless hidden), else the current bid; null = none. */
    public static function publicPrice(AuctionLot $lot): ?string
    {
        if ($lot->status === 'sold') {
            return $lot->hide_result_price ? null : $lot->hammer_price;
        }

        return $lot->current_price;
    }

    public static function priceHidden(AuctionLot $lot): bool
    {
        return $lot->status === 'sold' && $lot->hide_result_price;
    }

    /** null while nobody bid or there is no reserve */
    public static function reserve(AuctionLot $lot): ?string
    {
        return match ($lot->reserveMet()) {
            true => 'met',
            false => 'not_met',
            null => null,
        };
    }

    /** Lots whose time is up are closed on the spot, so nothing shows a lot open past its close. */
    public static function closeDue(Auction $auction): void
    {
        $close = app(CloseLot::class);
        $auction->lots()->where('status', 'ready')->where('ends_at', '<=', now())->get()->each(fn (AuctionLot $lot) => $close($lot));
    }
}
