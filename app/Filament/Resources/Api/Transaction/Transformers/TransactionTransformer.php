<?php

namespace App\Filament\Resources\Api\Transaction\Transformers;

use App\Models\Transaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Transaction $resource
 */
class TransactionTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
