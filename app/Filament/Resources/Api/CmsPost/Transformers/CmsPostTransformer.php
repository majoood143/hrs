<?php

namespace App\Filament\Resources\Api\CmsPost\Transformers;

use App\Models\CmsPost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsPost $resource
 */
class CmsPostTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
