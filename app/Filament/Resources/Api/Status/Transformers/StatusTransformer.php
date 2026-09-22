<?php

namespace App\Filament\Resources\Api\Status\Transformers;

use App\Models\Status;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Status $resource
 */
class StatusTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
