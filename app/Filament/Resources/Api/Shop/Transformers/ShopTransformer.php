<?php

namespace App\Filament\Resources\Api\Shop\Transformers;

use App\Models\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Shop $resource
 */
class ShopTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
