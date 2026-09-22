<?php

namespace App\Filament\Resources\Api\Pollination\Transformers;

use App\Models\Pollination;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Pollination $resource
 */
class PollinationTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
