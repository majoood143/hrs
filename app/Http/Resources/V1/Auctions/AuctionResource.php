<?php

namespace App\Http\Resources\V1\Auctions;

use App\Http\Resources\V1\MoneyResource;
use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\SiteSetting;
use App\Services\Auctions\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Auction */
class AuctionResource extends JsonResource
{
    /** The full auction (conditions, lots, the signed-in customer's registration), not the list card. */
    public bool $detail = false;

    public ?AuctionBidder $bidder = null;

    public static function detail(Auction $auction, ?AuctionBidder $bidder): self
    {
        $resource = new self($auction);
        $resource->detail = true;
        $resource->bidder = $bidder;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $organizer = $this->organizer;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            /** preview (catalogue only), live (taking bids), ended */
            'phase' => $this->phase(),
            /** Who runs it; null = the site itself (show the site's name and logo). */
            'organizer' => $organizer ? [
                'name' => $organizer->name,
                'logo_url' => $organizer->logo_url,
                /** @example #7a1f1f */
                'colour' => $organizer->colour,
            ] : null,
            'hosted_by' => $organizer?->name ?? SiteSetting::siteName(),
            'preview_at' => $this->preview_at?->toIso8601String(),
            'bidding_starts_at' => $this->bidding_starts_at?->toIso8601String(),
            /** When lot 1 closes; each lot has its own `ends_at`. */
            'ends_at' => $this->ends_at?->toIso8601String(),
            'lots_count' => (int) ($this->public_lots_count ?? $this->publicLots()->count()),
            'deposit' => (float) $this->deposit_amount > 0 ? new MoneyResource($this->deposit_amount) : null,
            'buyer_premium_percent' => (float) $this->buyer_premium_percent,
            'payment_days' => $this->payment_days,
            'share_url' => route('auctions.show', $this->slug),
            $this->mergeWhen($this->detail, fn () => [
                /** Sanitized HTML in the request's language. */
                'conditions_html' => $this->conditionsHtml(),
                'payment_note' => $this->payment_note,
                'lots' => LotListResource::collection($this->publicLots()->with('auction')->get()),
                /** The signed-in customer's registration; null when signed out or not registered. */
                'me' => $this->bidder ? [
                    /** pending, approved, rejected, blocked */
                    'status' => $this->bidder->status,
                    'paddle_number' => $this->bidder->paddle_number,
                    /** The open checkout of their deposit (the website's /pay page), if one waits. */
                    'deposit_pay_url' => ($order = app(Registration::class)->payableDeposit($this->bidder)) ? route('payment.start', $order->order_number) : null,
                ] : null,
            ]),
        ];
    }
}
