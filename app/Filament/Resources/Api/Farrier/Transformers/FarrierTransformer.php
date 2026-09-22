<?php

namespace App\Filament\Resources\Api\Farrier\Transformers;

use App\Models\Farrier;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Farrier $resource
 */
class FarrierTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
