<?php

namespace App\Filament\Resources\Api\MediaLibrary\Transformers;

use App\Models\MediaLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MediaLibrary $resource
 */
class MediaLibraryTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
