<?php

namespace App\Filament\Resources\Api\ShopService\Transformers;

use App\Models\ShopService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ShopService $resource
 */
class ShopServiceTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
