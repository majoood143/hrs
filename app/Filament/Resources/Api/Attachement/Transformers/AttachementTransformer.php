<?php

namespace App\Filament\Resources\Api\Attachement\Transformers;

use App\Models\Attachement;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Attachement $resource
 */
class AttachementTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
