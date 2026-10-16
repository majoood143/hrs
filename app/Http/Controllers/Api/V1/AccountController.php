<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Customer;
use App\Services\Auth\CustomerAccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in customer's own details. */
class AccountController extends Controller
{
    /** The signed-in customer */
    public function show(Request $request): CustomerResource
    {
        return CustomerResource::make($this->customer($request));
    }

    /**
     * Update name and email
     *
     * Used to fill in the booking form; the phone is the one signed in with and cannot change.
     */
    public function update(Request $request): CustomerResource
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $customer = $this->customer($request);
        $customer->fill($data)->save();

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
