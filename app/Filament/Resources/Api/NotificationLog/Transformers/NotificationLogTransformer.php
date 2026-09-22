<?php

namespace App\Filament\Resources\Api\NotificationLog\Transformers;

use App\Models\NotificationLog;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationLog $resource
 */
class NotificationLogTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
