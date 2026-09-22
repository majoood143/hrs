<?php

namespace App\Filament\Resources\Api\PaymentGatewayLog\Transformers;

use App\Models\PaymentGatewayLog;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PaymentGatewayLog $resource
 */
class PaymentGatewayLogTransformer extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
