<?php

namespace App\Services\Stables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\StablePackageActivated;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lesson packages: buying one (a service order paid online like a booking, with the same fee, VAT
 * and commission), switching it on when it is paid, and which of a customer's packages can pay for
 * a session. The sessions themselves are used by CreateStableBooking.
 */
class StablePackages
{
    public function __construct(private readonly StableBookingPricing $pricing) {}

    /** @throws BookingUnavailable */
    public function purchase(StablePackage $package, Customer $customer): StablePackagePurchase
    {
        $package->loadMissing(['stable', 'offering']);
        $stable = $package->stable;

        if (! $package->is_active || ! $package->offering?->is_active || ! $stable?->acceptsBookings()) {
            throw new BookingUnavailable(__('stable_packages.errors.not_available'));
        }

        [$purchase, $free] = DB::transaction(function () use ($package, $stable, $customer): array {
            $quote = $this->pricing->quotePackage($package);
            $account = $quote->isFree() ? null : $stable->activePaymentAccount();

            $order = ServiceOrder::create([
                'stable_id' => $stable->getKey(),
                'stable_payment_account_id' => $account?->getKey(),
                'collected_by' => $quote->isFree() ? null : ($account ? 'stable' : 'platform'),
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'locale' => app()->getLocale(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => $quote->isFree() ? OrderStatus::New : OrderStatus::PendingPayment,
                'payment_status' => $quote->isFree() ? PaymentStatus::Free : PaymentStatus::Pending,
            ] + $this->pricing->orderAttributes($quote, $stable));

            $purchase = StablePackagePurchase::create([
                'stable_id' => $stable->getKey(),
                'stable_package_id' => $package->getKey(),
                'stable_offering_id' => $package->stable_offering_id,
                'customer_id' => $customer->getKey(),
                'service_order_id' => $order->getKey(),
                'name' => $package->en_name,
                'sessions' => $package->sessions,
                'validity_days' => $package->validity_days,
            ]);

            $order->recordEvent('created', __('orders.events.created'), ['package' => $purchase->reference]);

            return [$purchase, $quote->isFree()];
        });

        if ($free) {
            $this->activate($purchase);
        }

        return $purchase->refresh();
    }

    /** Its order was paid (or it was free): the sessions can be used from now until it expires. */
    public function activate(StablePackagePurchase $purchase): bool
    {
        $activated = DB::transaction(function () use ($purchase): bool {
            $locked = StablePackagePurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->first();

            // a late payment revives a purchase whose checkout had expired
            if (! $locked || ! in_array($locked->status, [StablePackagePurchase::PENDING, StablePackagePurchase::CANCELLED], true)) {
                return false;
            }

            $locked->forceFill([
                'status' => StablePackagePurchase::ACTIVE,
                'activated_at' => now(),
                'expires_at' => now()->addDays($locked->validity_days)->endOfDay(),
            ])->save();

            return true;
        });

        if ($activated) {
            StablePackageActivated::dispatch($purchase->refresh());
        }

        return $activated;
    }

    /** The unpaid order was given up on (the expiry sweep, or the customer backing out). */
    public function orderCancelled(ServiceOrder $order): void
    {
        StablePackagePurchase::query()
            ->where('service_order_id', $order->getKey())
            ->where('status', StablePackagePurchase::PENDING)
            ->update(['status' => StablePackagePurchase::CANCELLED]);
    }

    /** @return Collection<int, StablePackagePurchase> the customer's packages that can pay for this service, with sessions left */
    public function usableFor(?Customer $customer, StableOffering $offering): Collection
    {
        if (! $customer) {
            return collect();
        }

        return StablePackagePurchase::query()
            ->usable()
            ->where('customer_id', $customer->getKey())
            ->where('stable_offering_id', $offering->getKey())
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (StablePackagePurchase $purchase) => $purchase->sessionsLeft() > 0)
            ->values();
    }
}
