<?php

namespace App\Filament\Resources\Api\StableService\Transformers;

use App\Models\StableService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property StableService $resource
 */
class StableServiceTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
