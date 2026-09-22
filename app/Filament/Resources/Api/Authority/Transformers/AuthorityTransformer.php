<?php

namespace App\Filament\Resources\Api\Authority\Transformers;

use App\Models\Authority;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Authority $resource
 */
class AuthorityTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
