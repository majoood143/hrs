<?php

namespace App\Filament\Resources\Api\SuccessStory\Transformers;

use App\Models\SuccessStory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SuccessStory $resource
 */
class SuccessStoryTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
