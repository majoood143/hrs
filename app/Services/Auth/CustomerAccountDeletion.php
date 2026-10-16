<?php

namespace App\Services\Auth;

use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\StableBooking;
use App\Models\StablePackagePurchase;
use App\Models\StableReview;
use Illuminate\Support\Facades\DB;

/**
 * A customer deletes their account from the app (the stores require it). Orders, bookings and
 * reviews are records the stables and the accounts need, so they stay with the name and phone they
 * were made with, unlinked from the account; signing in again with that phone links the orders back,
 * as for any guest order. Refused while something is still running for them (an upcoming booking, a
 * package with sessions left): they would lose sight of it.
 */
class CustomerAccountDeletion
{
    public function blocked(Customer $customer): bool
    {
        $upcoming = StableBooking::query()
            ->whereIn('status', [StableBookingStatus::Pending->value, StableBookingStatus::Confirmed->value])
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->getKey()))
            ->whereHas('slot', fn ($q) => $q->where('date', '>=', BookingSlot::today()))
            ->exists();

        $packages = StablePackagePurchase::query()
            ->usable()
            ->where('customer_id', $customer->getKey())
            ->get()
            ->contains(fn (StablePackagePurchase $purchase) => $purchase->sessionsLeft() > 0);

        return $upcoming || $packages;
    }

    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $id = $customer->getKey();

            ServiceOrder::query()->where('customer_id', $id)->update(['customer_id' => null]);
            StableBooking::query()->where('customer_id', $id)->update(['customer_id' => null]);
            StableReview::query()->where('customer_id', $id)->update(['customer_id' => null]);

            $customer->tokens()->delete();
            $customer->delete();
        });
    }
}
