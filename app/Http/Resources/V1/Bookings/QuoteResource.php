<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Models\StableExtra;
use App\Services\Orders\PriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a booking or package costs: the price, the service fee and VAT on top, and the total.
 *
 * @property-read PriceBreakdown $resource
 */
class QuoteResource extends JsonResource
{
    /** @var list<array{extra: StableExtra, quantity: int, amount: int}> */
    private array $extras = [];

    /** The extras chosen (`StableExtra::lines()`), shown as rows of their own. */
    public function withExtras(array $lines): static
    {
        $this->extras = $lines;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $quote = $this->resource;
        $extras = StableExtra::totalBaisa($this->extras);

        return [
            /** The session (riders × price) before the discount, without the extras. */
            'session' => new MoneyResource(PriceBreakdown::toOmr($quote->price + $quote->discount - $extras)),
            /** The extras chosen; their total is in `price`. */
            'extras' => ExtraLineResource::collection($this->extras),
            /** @var MoneyResource|null null without extras */
            'extras_total' => $extras > 0 ? new MoneyResource(PriceBreakdown::toOmr($extras)) : null,
            /** Session + extras, after the promo code's discount, if any. */
            'price' => new MoneyResource(PriceBreakdown::toOmr($quote->price)),
            /** The promo code's discount (already taken off `price`); null without one. */
            'discount' => $quote->discount > 0 ? new MoneyResource(PriceBreakdown::toOmr($quote->discount)) : null,
            'fee' => new MoneyResource(PriceBreakdown::toOmr($quote->fee)),
            /** VAT on the price and on the fee together. */
            'vat' => new MoneyResource(PriceBreakdown::toOmr($quote->vatOnPrice + $quote->vatOnFee)),
            /** @example 5 */
            'vat_rate' => (float) $quote->vatRate,
            'total' => new MoneyResource(PriceBreakdown::toOmr($quote->total())),
            'is_free' => $quote->isFree(),
        ];
    }
}
