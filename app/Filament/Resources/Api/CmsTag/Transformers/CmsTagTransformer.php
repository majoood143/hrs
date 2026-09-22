<?php

namespace App\Filament\Resources\Api\CmsTag\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsTag $resource
 */
class CmsTagTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
