<?php

namespace App\Filament\Resources\Api\Clinic\Transformers;

use App\Models\Clinic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Clinic $resource
 */
class ClinicTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
