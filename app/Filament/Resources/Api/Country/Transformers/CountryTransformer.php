<?php

namespace App\Filament\Resources\Api\Country\Transformers;

use App\Models\Country;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Country $resource
 */
class CountryTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
