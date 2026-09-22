<?php

namespace App\Filament\Resources\Api\Center\Transformers;

use App\Models\Center;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Center $resource
 */
class CenterTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
