<?php

namespace App\Http\Resources\V1;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The site's display currency (General Settings). Show `icon_url` when set, else `symbol`
 * (the website's `<x-currency-symbol>` rule); never a hardcoded "OMR".
 */
class CurrencyResource extends JsonResource
{
    public static function current(): self
    {
        return new self(SiteSetting::currency());
    }

    /**
     * @return array{code: string, symbol: string, icon_url: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => (string) $this->resource['code'],
            'symbol' => (string) $this->resource['symbol'],
            'icon_url' => $this->resource['icon_url'],
        ];
    }
}
