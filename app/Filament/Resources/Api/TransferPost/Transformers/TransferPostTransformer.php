<?php

namespace App\Filament\Resources\Api\TransferPost\Transformers;

use App\Models\TransferPost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TransferPost $resource
 */
class TransferPostTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
