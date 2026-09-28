<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingPricing;
use App\Services\Stables\StablePackages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** A signed-in customer's lesson packages, and buying one (a package is tied to its buyer's account). */
class AccountPackageController extends Controller
{
    public function index(): View
    {
        return view('site.account.packages', [
            'customer' => $this->customer(),
            'purchases' => StablePackagePurchase::query()
                ->where('customer_id', $this->customer()->getKey())
                ->whereIn('status', [StablePackagePurchase::ACTIVE, StablePackagePurchase::PENDING])
                ->with(['stable', 'offering', 'package', 'order'])
                ->latest('id')
                ->get(),
            'seoTitle' => __('stable_packages.account.title'),
            'noindex' => true,
        ]);
    }

    public function buy(StablePackage $package, StableBookingPricing $pricing): View
    {
        $package->loadMissing(['stable', 'offering']);
        abort_unless($package->is_active && $package->stable?->acceptsBookings() && $package->offering?->is_active, 404);

        return view('site.account.package-buy', [
            'package' => $package,
            'quote' => $pricing->quotePackage($package),
            'seoTitle' => $package->name.' — '.SiteSetting::siteName(),
            'noindex' => true,
        ]);
    }

    public function purchase(StablePackage $package, StablePackages $packages): RedirectResponse
    {
        try {
            $purchase = $packages->purchase($package, $this->customer());
        } catch (BookingUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        $order = $purchase->order;

        return $order->isPayable()
            ? redirect()->route('payment.start', $order->order_number)
            : redirect()->route('account.packages')->with('status', __('stable_packages.account.ready'));
    }

    private function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }
}
