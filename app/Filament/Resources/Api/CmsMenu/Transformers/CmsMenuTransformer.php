<?php

namespace App\Filament\Resources\Api\CmsMenu\Transformers;

use App\Models\CmsMenu;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsMenu $resource
 */
class CmsMenuTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
