<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Models\StablePackage;
use App\Services\Orders\PriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lesson package a stable sells: a number of sessions of one service, used within its validity.
 *
 * @property-read StablePackage $resource
 */
class StablePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $package = $this->resource;
        $saving = $package->savingBaisa();

        return [
            'id' => (int) $package->id,
            'name' => (string) $package->name,
            /** @var string|null */
            'description' => $package->description,
            'sessions' => (int) $package->sessions,
            'validity_days' => (int) $package->validity_days,
            /** Before the service fee and VAT. */
            'price' => new MoneyResource($package->price),
            /** Against booking the sessions one by one; null when there is none. */
            'saving' => $saving > 0 ? new MoneyResource(PriceBreakdown::toOmr($saving)) : null,
            'offering_id' => (int) $package->stable_offering_id,
            /** @var string|null */
            'offering_name' => $package->offering?->name,
        ];
    }
}
