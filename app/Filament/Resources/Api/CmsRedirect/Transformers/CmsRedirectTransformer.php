<?php

namespace App\Filament\Resources\Api\CmsRedirect\Transformers;

use App\Models\CmsRedirect;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CmsRedirect $resource
 */
class CmsRedirectTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
