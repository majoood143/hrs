<?php

namespace App\Filament\Resources\Api\Service\Transformers;

use App\Models\Service;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Service $resource
 */
class ServiceTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
