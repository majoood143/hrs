<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Services\Auctions\Applications;
use App\Services\Auctions\AuctionBidException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** /auctions/sell: a signed-in customer applies to put their horse in an auction. */
class AuctionApplicationController extends Controller
{
    public function create(): View
    {
        return view('site.auctions.sell', [
            'customer' => $this->customer(),
            'seoTitle' => __('auctions.sell.title').' — '.SiteSetting::siteName(),
            'seoDescription' => __('auctions.sell.intro'),
        ]);
    }

    public function store(Request $request, Applications $applications): RedirectResponse
    {
        $data = $request->validate($applications->rules(), [], $applications->attributes());

        try {
            $applications->submit($this->customer(), $data, $request->file('photos', []), $request->file('documents', []));
        } catch (AuctionBidException $e) {
            return back()->withErrors(['horse_name' => $e->getMessage()])->withInput();
        }

        return redirect()->route('account.auctions')->with('status', __('auctions.sell.sent'));
    }

    private function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }
}
