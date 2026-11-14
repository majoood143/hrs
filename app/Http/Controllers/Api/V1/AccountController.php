<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerResource;
use App\Models\AppEvent;
use App\Models\Customer;
use App\Services\Auth\CustomerAccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The signed-in customer's own details. */
class AccountController extends Controller
{
    /** The signed-in customer */
    public function show(Request $request): CustomerResource
    {
        return CustomerResource::make($this->customer($request));
    }

    /**
     * Update the profile
     *
     * Name and email (the booking form), and the optional profile: gender, date of birth, ID number,
     * marketing consent. Send only the fields that change (a field left out stays as it is; `null`
     * clears it). The phone is the one signed in with and cannot change.
     */
    public function update(Request $request): CustomerResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'gender' => ['sometimes', 'nullable', Rule::in(Customer::GENDERS)],
            /** `Y-m-d`. */
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            /** Letters and digits. @example 12345678 */
            'national_id' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        $customer = $this->customer($request);
        $customer->fill($data);
        $changed = array_keys($customer->getDirty());
        $customer->save();

        if ($changed !== []) {
            // which fields changed, never their values (the CRM shows the profile itself)
            AppEvent::record('profile_updated', $customer, ['fields' => implode(',', $changed), 'completion' => $customer->profileCompletion()], $request);
        }

        return CustomerResource::make($customer);
    }

    /**
     * Delete the account
     *
     * Signs out everywhere. Orders and bookings stay with the stables (unlinked from the account).
     * 409 `account_busy` while an upcoming booking or a package with sessions left is still running.
     */
    public function destroy(Request $request, CustomerAccountDeletion $deletion): JsonResponse
    {
        $customer = $this->customer($request);

        if ($deletion->blocked($customer)) {
            return response()->json(['message' => __('api.account.delete_blocked'), 'code' => 'account_busy'], 409);
        }

        $deletion->delete($customer);

        return response()->json(['message' => __('api.account.deleted')]);
    }

    private function customer(Request $request): Customer
    {
        return $request->user('customer_api');
    }
}
