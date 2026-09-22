<?php

namespace App\Filament\Resources\Api\Partner\Transformers;

use App\Models\Partner;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Partner $resource
 */
class PartnerTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
