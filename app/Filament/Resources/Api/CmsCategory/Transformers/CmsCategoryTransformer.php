<?php

namespace App\Filament\Resources\Api\CmsCategory\Transformers;

use App\Models\CmsCategory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsCategory $resource
 */
class CmsCategoryTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
