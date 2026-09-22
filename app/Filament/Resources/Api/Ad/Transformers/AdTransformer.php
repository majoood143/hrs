<?php

namespace App\Filament\Resources\Api\Ad\Transformers;

use App\Models\Ad;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Ad $resource
 */
class AdTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
