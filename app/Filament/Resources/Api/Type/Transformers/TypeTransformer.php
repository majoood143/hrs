<?php

namespace App\Filament\Resources\Api\Type\Transformers;

use App\Models\Type;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Type $resource
 */
class TypeTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
