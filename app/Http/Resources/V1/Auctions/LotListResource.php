<?php

namespace App\Http\Resources\V1\Auctions;

use App\Http\Resources\V1\MoneyResource;
use App\Models\AuctionLot;
use App\Services\Auctions\LotState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A lot as a card (catalogue, my bids, watchlist). @mixin AuctionLot */
class LotListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $price = LotState::publicPrice($this->resource);

        return [
            'id' => $this->id,
            'number' => $this->lot_number,
            'auction_slug' => $this->auction->slug,
            'auction_title' => $this->auction->title,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'photo_url' => $this->photoUrls()[0] ?? null,
            /** ready (in the catalogue), sold, unsold, withdrawn */
            'status' => $this->status,
            'open' => $this->isOpenAt(now()),
            'gender' => $this->gender ? ['key' => $this->gender, 'label' => __('auctions.genders.'.$this->gender)] : null,
            'born_year' => $this->born_on?->year,
            'sire' => $this->sire,
            'dam' => $this->dam,
            /** The current bid, or the hammer price once sold; null when there is none or it is hidden. */
            'price' => $price === null ? null : new MoneyResource($price),
            'price_hidden' => LotState::priceHidden($this->resource),
            'starting_price' => new MoneyResource($this->starting_price),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'bids' => $this->bids_count,
            'bidders' => $this->bidders_count,
            'share_url' => $this->url(),
        ];
    }
}
