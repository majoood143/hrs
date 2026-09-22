<?php

namespace App\Filament\Resources\Api\City\Transformers;

use App\Models\City;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property City $resource
 */
class CityTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
