<?php

namespace App\Http\Resources\V1\Auctions;

use App\Http\Resources\V1\MoneyResource;
use App\Models\AuctionBid;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Services\Auctions\LotState;
use App\Services\Auctions\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The bid board, polled every few seconds while the lot is on screen. Countdowns run on
 * `server_now` (the phone's clock may be wrong). Never the reserve or anyone else's maximum.
 *
 * @mixin AuctionLot
 */
class LotStateResource extends JsonResource
{
    public function __construct(AuctionLot $lot, private readonly ?AuctionBidder $bidder = null)
    {
        parent::__construct($lot);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lot = $this->resource;
        $lot->loadMissing(['auction', 'leadingBid']);
        $open = $lot->isOpenAt(now());
        $you = LotState::you($lot, $this->bidder);
        $price = LotState::publicPrice($lot);
        $minimum = $lot->minimumNextBid();
        $invoice = $you === 'won' ? $lot->invoice : null;

        return [
            'status' => $lot->status,
            'open' => $open,
            /** In the catalogue, bidding not open yet. */
            'not_started' => $lot->status === 'ready' && ! $open && (bool) $lot->ends_at?->isFuture(),
            'price' => $price === null ? null : new MoneyResource($price),
            'price_hidden' => LotState::priceHidden($lot),
            'starting_price' => new MoneyResource($lot->starting_price),
            /** The lowest bid anyone but the leader may place now. */
            'minimum' => new MoneyResource(Money::omr($minimum)),
            'step' => new MoneyResource(Money::omr($lot->auction->steps()->after($lot->current_price === null ? Money::baisa($lot->starting_price) : Money::baisa($lot->current_price)))),
            'ends_at' => $lot->ends_at?->toIso8601String(),
            'bidding_starts_at' => $lot->auction->bidding_starts_at?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'bids' => $lot->bids_count,
            'bidders' => $lot->bidders_count,
            /** met, not_met, or null (no reserve, or no bid yet) */
            'reserve' => LotState::reserve($lot),
            /** leading, outbid, won, lost, or null */
            'you' => $you,
            'your_max' => $you === 'leading' ? new MoneyResource($lot->leadingBid->max_amount) : null,
            /** The winner's invoice. */
            'invoice' => $invoice ? [
                /** The website's checkout: open it in the app's payment sheet. */
                'pay_url' => $invoice->isPayable() ? route('payment.start', $invoice->order_number) : null,
                'paid' => $invoice->isPaid(),
                'total' => new MoneyResource($invoice->total),
                'due_at' => $lot->payment_due_at?->toIso8601String(),
                'order_number' => $invoice->order_number,
            ] : null,
            'history' => $lot->bids()->visible()->with('bidder')->latest('id')->limit(10)->get()->map(fn (AuctionBid $bid) => [
                'bidder' => $bid->bidder?->label(),
                'mine' => $this->bidder !== null && (int) $bid->auction_bidder_id === (int) $this->bidder->id,
                'amount' => new MoneyResource($bid->amount),
                'at' => $bid->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
