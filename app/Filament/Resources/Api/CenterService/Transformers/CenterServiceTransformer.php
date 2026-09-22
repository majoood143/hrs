<?php

namespace App\Filament\Resources\Api\CenterService\Transformers;

use App\Models\CenterService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CenterService $resource
 */
class CenterServiceTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
