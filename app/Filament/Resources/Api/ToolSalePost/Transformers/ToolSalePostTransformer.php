<?php

namespace App\Filament\Resources\Api\ToolSalePost\Transformers;

use App\Models\ToolSalePost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ToolSalePost $resource
 */
class ToolSalePostTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
