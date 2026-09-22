<?php

namespace App\Filament\Resources\Api\Vaccination\Transformers;

use App\Models\Vaccination;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Vaccination $resource
 */
class VaccinationTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
