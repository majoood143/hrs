<?php

namespace App\Filament\Resources\Api\Event\Transformers;

use App\Models\Event;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Event $resource
 */
class EventTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
