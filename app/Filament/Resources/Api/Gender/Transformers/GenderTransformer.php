<?php

namespace App\Filament\Resources\Api\Gender\Transformers;

use App\Models\Gender;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Gender $resource
 */
class GenderTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
