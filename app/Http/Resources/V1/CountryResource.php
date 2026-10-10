<?php

namespace App\Http\Resources\V1;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A public country with its regions and their cities (the website's location filter tree).
 *
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            /** ISO 3166-1 alpha-2, upper case. */
            'code' => $this->country_code,
            'flag_url' => $this->flag_url,
            'regions' => RegionResource::collection($this->whenLoaded('region')),
        ];
    }
}
