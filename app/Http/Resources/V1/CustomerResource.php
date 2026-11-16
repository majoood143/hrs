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
            /** Absolute URL. @var string|null */
            'photo_url' => $customer->profilePhotoUrl(),
            /** The phone's country, from the sign-in: ISO 3166 alpha-2. @var string|null @example OM */
            'country' => $customer->country,
            /** Where they live: a public country of `GET lookups`. @var int|null */
            'country_id' => $customer->country_id,
            /** One of that country's regions. @var int|null */
            'region_id' => $customer->region_id,
            /** @var 'male'|'female'|null */
            'gender' => $customer->gender,
            /** `Y-m-d`. @var string|null */
            'date_of_birth' => $customer->date_of_birth?->toDateString(),
            /** Only the last 4 digits; the number itself is never sent back. @var string|null @example ••••4567 */
            'national_id' => $customer->maskedNationalId(),
            'marketing_opt_in' => (bool) $customer->marketing_opt_in,
            /** How much of the optional profile is filled in over the name and the fields the admin shows (`app-status.profile_fields`), 0–100. */
            'profile_completion' => $customer->profileCompletion(),
        ];
    }
}
