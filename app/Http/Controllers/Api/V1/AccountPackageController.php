<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Bookings\PackagePurchaseResource;
use App\Models\Customer;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StablePackages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Lesson packages: the signed-in customer's purchases, and buying one (`StablePackages`, as on the site). */
class AccountPackageController extends Controller
{
    /** My packages (newest first; awaiting payment, active, expired and cancelled). */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PackagePurchaseResource::collection(
            StablePackagePurchase::query()
                ->where('customer_id', $this->customer($request)->getKey())
                ->with(['package', 'offering', 'stable', 'order'])
                ->latest('id')
                ->limit(100)
                ->get()
        );
    }

    /**
     * Buy a package
     *
     * Answers 201 with the purchase: `pay_url` while it waits for the online payment (it is switched
     * on when paid); a free one is active at once. 409 `booking_unavailable` when it is not sold now.
     */
    public function buy(Request $request, int $id, StablePackages $packages): JsonResponse
    {
        $package = StablePackage::query()->findOrFail($id);

        try {
            $purchase = $packages->purchase($package, $this->customer($request));
        } catch (BookingUnavailable $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'booking_unavailable'], 409);
        }

        return PackagePurchaseResource::make($purchase->load(['package', 'offering', 'stable', 'order']))->response()->setStatusCode(201);
    }

    private function customer(Request $request): Customer
    {
        return $request->user('customer_api');
    }
}
