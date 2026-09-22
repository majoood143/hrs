<?php

namespace App\Filament\Resources\Api\EventCategory\Transformers;

use App\Models\EventCategory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EventCategory $resource
 */
class EventCategoryTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
