<?php

namespace App\Filament\Resources\Api\ClinicService\Transformers;

use App\Models\ClinicService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ClinicService $resource
 */
class ClinicServiceTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
