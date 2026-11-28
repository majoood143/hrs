<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\SiteSetting;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingPricing;
use App\Services\Stables\StablePackages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function buy(Request $request, StablePackage $package, StableBookingPricing $pricing): View
    {
        $package->loadMissing(['stable', 'offering']);
        abort_unless($package->is_active && $package->stable?->acceptsBookings() && $package->offering?->is_active, 404);

        $code = is_string($request->query('promo_code')) ? PromoCode::normalize($request->query('promo_code')) : null;
        [$promo, $promoError] = [null, null];

        try {
            $promo = $pricing->promo($code, $package->stable, $this->customer()->phone);
        } catch (BookingUnavailable $e) {
            $promoError = $e->getMessage();
        }

        return view('site.account.package-buy', [
            'package' => $package,
            'quote' => $pricing->quotePackage($package, $promo),
            'promoCodes' => PromoCode::enabled(),
            'promoCode' => $code,
            'promoError' => $promoError,
            'seoTitle' => $package->name.' — '.SiteSetting::siteName(),
            'noindex' => true,
        ]);
    }

    public function purchase(Request $request, StablePackage $package, StablePackages $packages): RedirectResponse
    {
        $data = $request->validate(['promo_code' => ['nullable', 'string', 'max:50']]);

        try {
            $purchase = $packages->purchase($package, $this->customer(), $data['promo_code'] ?? null);
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
