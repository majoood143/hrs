<?php

namespace App\Http\Resources\V1;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An amount in the site's currency: a 3-decimal string (never a float) for sums, the same amount
 * written the way General Settings says (`formatted`, what to show) and the currency.
 */
class MoneyResource extends JsonResource
{
    public function __construct(float|int|string|null $amount)
    {
        parent::__construct((string) ($amount ?? 0));
    }

    /**
     * @return array{amount: string, formatted: string, currency: CurrencyResource}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @example 1250.500 */
            'amount' => number_format((float) $this->resource, 3, '.', ''),
            /** @example 1,250.500 */
            'formatted' => SiteSetting::formatAmount($this->resource),
            'currency' => CurrencyResource::current(),
        ];
    }
}
