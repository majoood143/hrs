<?php

namespace App\Filament\Resources\Api\Color\Transformers;

use App\Models\Color;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Color $resource
 */
class ColorTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
