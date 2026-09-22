<?php

namespace App\Filament\Resources\Api\CommissionSetting\Transformers;

use App\Models\CommissionSetting;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CommissionSetting $resource
 */
class CommissionSettingTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
