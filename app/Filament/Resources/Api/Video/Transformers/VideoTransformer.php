<?php

namespace App\Filament\Resources\Api\Video\Transformers;

use App\Models\Video;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Video $resource
 */
class VideoTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
