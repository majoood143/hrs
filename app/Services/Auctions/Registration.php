<?php

namespace App\Services\Auctions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Orders\PriceBreakdown;
use Illuminate\Support\Facades\DB;

/**
 * A customer registering to bid: they accept the auction's conditions and pay its deposit (a
 * `ServiceOrder` through the usual `/pay/{order}` checkout, so every enabled gateway works). The
 * registration is approved at once when the auction auto-approves and nothing is left to pay,
 * otherwise an admin approves it (Admin → Auctions → the auction → Bidders).
 */
class Registration
{
    public function register(Auction $auction, Customer $customer): AuctionBidder
    {
        if ($auction->status !== 'published' || $auction->phase() === 'ended') {
            throw new AuctionBidException('registration_closed');
        }

        return DB::transaction(function () use ($auction, $customer) {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            $bidder = AuctionBidder::query()->firstOrNew(['auction_id' => $auction->id, 'customer_id' => $customer->id]);

            if (in_array($bidder->status, ['rejected', 'blocked'], true)) {
                throw new AuctionBidException('registration_refused');
            }
            if ($bidder->status === 'approved') {
                return $bidder;
            }

            $bidder->fill([
                'status' => 'pending',
                'locale' => app()->getLocale(),
                'accepted_terms_at' => now(),
                'terms_ip' => request()->ip(),
            ])->save();

            if ($this->depositDue($auction, $bidder)) {
                $bidder->update(['deposit_order_id' => $this->depositOrder($auction, $bidder, $customer)->id]);
            } elseif ($auction->auto_approve && ! $this->payableDeposit($bidder)) {
                // nothing to pay (no deposit, or it is paid already)
                $bidder->update(['status' => 'approved', 'approved_at' => now()]);
            }

            return $bidder->refresh();
        });
    }

    /** Its deposit order was paid: approve it when the auction says so (else it waits for an admin). */
    public function depositPaid(ServiceOrder $order): void
    {
        $bidder = AuctionBidder::query()->with('auction')->find($order->auction_bidder_id);

        if ($bidder && $bidder->status === 'pending' && $bidder->auction->auto_approve && (int) $bidder->deposit_order_id === (int) $order->id) {
            $bidder->update(['status' => 'approved', 'approved_at' => now()]);
        }
    }

    /** A deposit is asked and this registration has no paid one, and no checkout still open. */
    public function depositDue(Auction $auction, AuctionBidder $bidder): bool
    {
        if ((float) $auction->deposit_amount <= 0) {
            return false;
        }

        $order = $bidder->deposit_order_id ? ServiceOrder::query()->find($bidder->deposit_order_id) : null;

        return ! $order || ! ($order->isPaid() || $order->isPayable());
    }

    /** The open checkout of this registration's deposit, if one waits to be paid. */
    public function payableDeposit(AuctionBidder $bidder): ?ServiceOrder
    {
        $order = $bidder->deposit_order_id ? ServiceOrder::query()->find($bidder->deposit_order_id) : null;

        return $order?->isPayable() ? $order : null;
    }

    private function depositOrder(Auction $auction, AuctionBidder $bidder, Customer $customer): ServiceOrder
    {
        // a deposit is held, not earned: no service fee, no VAT
        $money = new PriceBreakdown(Money::baisa($auction->deposit_amount), 0, 0, 0, 0.0, SiteSetting::currency()['code']);

        $order = ServiceOrder::create([
            'auction_bidder_id' => $bidder->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'locale' => app()->getLocale(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ] + $money->toOrderAttributes());

        $order->recordEvent('created', __('orders.events.created'), ['auction' => $auction->slug]);

        return $order;
    }
}
