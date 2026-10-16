<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
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
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'price' => new MoneyResource(PriceBreakdown::toOmr($quote->price)),
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
