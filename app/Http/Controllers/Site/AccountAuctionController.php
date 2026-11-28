<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AuctionApplication;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\AuctionWatch;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** A signed-in customer's auctions: where they are registered, the lots they bid on and their watchlist. */
class AccountAuctionController extends Controller
{
    public function index(): View
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $registrations = AuctionBidder::query()->where('customer_id', $customer->id)
            ->whereHas('auction', fn ($q) => $q->where('status', '!=', 'draft'))
            ->with(['auction', 'depositOrder'])->latest('id')->get();

        $bidderIds = $registrations->pluck('id');
        $lots = AuctionLot::query()->with(['auction', 'leadingBid', 'invoice'])
            ->whereHas('bids', fn ($q) => $q->valid()->whereIn('auction_bidder_id', $bidderIds))
            ->orderByDesc('ends_at')->get()
            ->map(function (AuctionLot $lot) use ($bidderIds) {
                $leader = $lot->leadingBid ? (int) $lot->leadingBid->auction_bidder_id : null;
                $lot->setAttribute('my_status', match (true) {
                    $lot->status === 'sold' && $bidderIds->contains($lot->winner_bidder_id) => 'won',
                    in_array($lot->status, ['sold', 'unsold'], true) => 'lost',
                    $bidderIds->contains($leader) => 'leading',
                    default => 'outbid',
                });

                return $lot;
            });

        $selling = AuctionLot::query()->with(['auction', 'invoice'])->where('seller_customer_id', $customer->id)
            ->whereHas('auction', fn ($q) => $q->where('status', 'published'))->where('status', '!=', 'draft')
            ->orderByDesc('ends_at')->get();

        return view('site.account.auctions', [
            'applications' => AuctionApplication::query()->where('customer_id', $customer->id)->with('lot.auction')->latest('id')->get(),
            'selling' => $selling,
            'registrations' => $registrations,
            'lots' => $lots,
            'watches' => AuctionWatch::query()->where('customer_id', $customer->id)
                ->whereHas('lot', fn ($q) => $q->where('status', 'ready'))
                ->whereNotIn('auction_lot_id', $lots->pluck('id'))
                ->with('lot.auction')->get(),
            'seoTitle' => __('auctions.site.account_title'),
            'noindex' => true,
        ]);
    }
}
