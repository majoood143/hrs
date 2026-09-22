<?php

namespace App\Filament\Resources\Api\Region\Transformers;

use App\Models\Region;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Region $resource
 */
class RegionTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
