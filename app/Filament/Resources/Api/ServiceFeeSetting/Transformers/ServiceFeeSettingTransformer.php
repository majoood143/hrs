<?php

namespace App\Filament\Resources\Api\ServiceFeeSetting\Transformers;

use App\Models\ServiceFeeSetting;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ServiceFeeSetting $resource
 */
class ServiceFeeSettingTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
