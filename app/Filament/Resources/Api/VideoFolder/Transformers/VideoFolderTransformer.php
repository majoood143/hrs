<?php

namespace App\Filament\Resources\Api\VideoFolder\Transformers;

use App\Models\VideoFolder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VideoFolder $resource
 */
class VideoFolderTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
