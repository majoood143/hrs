<?php

namespace App\Filament\Resources\Api\HorseSalePost\Transformers;

use App\Models\HorseSalePost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property HorseSalePost $resource
 */
class HorseSalePostTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
