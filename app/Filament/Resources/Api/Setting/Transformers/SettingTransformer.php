<?php

namespace App\Filament\Resources\Api\Setting\Transformers;

use App\Models\Setting;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Setting $resource
 */
class SettingTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
