<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An amount in the site's currency: a 3-decimal string (never a float) plus the currency to show.
 */
class MoneyResource extends JsonResource
{
    public function __construct(float|int|string|null $amount)
    {
        parent::__construct((string) ($amount ?? 0));
    }

    /**
     * @return array{amount: string, currency: CurrencyResource}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @example 1250.500 */
            'amount' => number_format((float) $this->resource, 3, '.', ''),
            'currency' => CurrencyResource::current(),
        ];
    }
}
