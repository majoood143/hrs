<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerResource;
use App\Models\AppEvent;
use App\Models\Customer;
use App\Services\Auth\CustomerAccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
     * country and region (a public country from `GET lookups`, one of its regions), marketing consent.
     * Send only the fields that change (a field left out stays as it is; `null` clears it). A new
     * country clears a region that is not in it. The phone is the one signed in with and cannot change.
     */
    public function update(Request $request): CustomerResource
    {
        $customer = $this->customer($request);
        $countryId = $request->has('country_id') ? $request->input('country_id') : $customer->country_id;

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'gender' => ['sometimes', 'nullable', Rule::in(Customer::GENDERS)],
            /** `Y-m-d`. */
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            /** Letters and digits. @example 12345678 */
            'national_id' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')->where('is_public', true)],
            'region_id' => ['sometimes', 'nullable', 'integer', Rule::exists('regions', 'id')->where('country_id', $countryId)],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        $customer->fill($data);

        if ($customer->isDirty('country_id') && ! array_key_exists('region_id', $data)) {
            $customer->region_id = null;
        }

        $changed = array_keys($customer->getDirty());
        $customer->save();

        if ($changed !== []) {
            // which fields changed, never their values (the CRM shows the profile itself)
            AppEvent::record('profile_updated', $customer, ['fields' => implode(',', $changed), 'completion' => $customer->profileCompletion()], $request);
        }

        return CustomerResource::make($customer);
    }

    /**
     * Change the profile photo
     *
     * `photo` is a JPG, PNG or WebP image (up to 4 MB); send multipart/form-data. Replaces the earlier one.
     * 422 with one message for any refused file (`api.account.photo_invalid`).
     */
    public function photo(Request $request): CustomerResource
    {
        // one clear message for every way a file can be wrong (missing, not an image, too big, cut off)
        $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [
            'photo.*' => __('api.account.photo_invalid'),
        ]);

        $customer = $this->customer($request);
        $old = $customer->profile_photo;
        $customer->update(['profile_photo' => $request->file('photo')->store('customers/photos', 'public')]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        AppEvent::record('profile_updated', $customer, ['fields' => 'profile_photo', 'completion' => $customer->profileCompletion()], $request);

        return CustomerResource::make($customer);
    }

    /** Remove the profile photo */
    public function deletePhoto(Request $request): CustomerResource
    {
        $customer = $this->customer($request);

        if ($customer->profile_photo) {
            Storage::disk('public')->delete($customer->profile_photo);
            $customer->update(['profile_photo' => null]);
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
