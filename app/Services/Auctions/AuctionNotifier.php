<?php

namespace App\Services\Auctions;

use App\Jobs\SendAuctionMessage;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\AuctionWatch;
use Illuminate\Support\Facades\Cache;

/** Which auction messages go out and when; the job writes and sends them after the change is saved. */
class AuctionNotifier
{
    /** At most one "outbid" SMS per bidder and lot in this many minutes (a bidding war would flood them). */
    public const OUTBID_QUIET_MINUTES = 10;

    /** "Closes soon" goes to watchers this many minutes before a lot closes. */
    public const ENDING_MINUTES = 15;

    public function approved(AuctionBidder $bidder): void
    {
        SendAuctionMessage::dispatch(SendAuctionMessage::APPROVED, $bidder->id)->afterCommit();
    }

    public function outbid(int $bidderId, AuctionLot $lot): void
    {
        if (Cache::add("auction-outbid:{$lot->id}:{$bidderId}", true, now()->addMinutes(self::OUTBID_QUIET_MINUTES))) {
            SendAuctionMessage::dispatch(SendAuctionMessage::OUTBID, $bidderId, $lot->id)->afterCommit();
        }
    }

    public function won(AuctionLot $lot): void
    {
        SendAuctionMessage::dispatch(SendAuctionMessage::WON, $lot->winner_bidder_id, $lot->id)->afterCommit();
    }

    /** @return int how many watchers were told */
    public function endingSoon(): int
    {
        $watches = AuctionWatch::query()
            ->whereNull('ending_notified_at')
            ->whereHas('lot', fn ($q) => $q->where('status', 'ready')
                ->whereBetween('ends_at', [now(), now()->addMinutes(self::ENDING_MINUTES)])
                ->whereHas('auction', fn ($a) => $a->where('status', 'published')))
            ->get();

        foreach ($watches as $watch) {
            $watch->update(['ending_notified_at' => now()]);
            SendAuctionMessage::dispatch(SendAuctionMessage::ENDING, $watch->id);
        }

        return $watches->count();
    }
}
