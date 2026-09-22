<?php

namespace App\Filament\Resources\Api\CmsPage\Transformers;

use App\Models\CmsPage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsPage $resource
 */
class CmsPageTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
