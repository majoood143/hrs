<?php

namespace App\Filament\Resources\Api\AdZone\Transformers;

use App\Models\AdZone;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AdZone $resource
 */
class AdZoneTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
