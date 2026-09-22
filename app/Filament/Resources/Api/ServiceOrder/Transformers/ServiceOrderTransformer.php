<?php

namespace App\Filament\Resources\Api\ServiceOrder\Transformers;

use App\Models\ServiceOrder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ServiceOrder $resource
 */
class ServiceOrderTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
