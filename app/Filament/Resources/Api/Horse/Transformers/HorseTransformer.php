<?php

namespace App\Filament\Resources\Api\Horse\Transformers;

use App\Models\Horse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Horse $resource
 */
class HorseTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
