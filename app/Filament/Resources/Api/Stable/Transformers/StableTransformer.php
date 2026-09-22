<?php

namespace App\Filament\Resources\Api\Stable\Transformers;

use App\Models\Stable;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Stable $resource
 */
class StableTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
