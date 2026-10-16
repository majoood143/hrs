<?php

namespace App\Http\Resources\V1;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in customer (phone sign-in; no password).
 *
 * @property-read Customer $resource
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => (int) $customer->id,
            /** Digits with the country code. @example 96899887766 */
            'phone' => (string) $customer->phone,
            /** @var string|null */
            'name' => $customer->name,
            /** @var string|null */
            'email' => $customer->email,
        ];
    }
}
