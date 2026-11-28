<?php

namespace App\Services\Auctions;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Jobs\SendAuctionMessage;
use App\Models\AuctionBid;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\OrderRefundService;
use App\Services\Orders\PriceBreakdown;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * After the hammer: the winner's invoice (a `ServiceOrder` with `auction_lot_id`, paid at
 * `/pay/{order}` or by a bank transfer an admin records), its reminder and overdue flag, the offer to
 * the next bidder when it is not paid, the hand-over, the seller's payout and the deposits.
 *
 * The invoice is an ordinary order, so receipts, refunds and the income report work as they do for
 * everything else: price = hammer (+ the lot's VAT on it), fee = the buyer's premium (+ VAT), and the
 * seller's commission + entry fee = the order's commission, taken from the seller's share.
 */
class Settlement
{
    public function __construct(
        private readonly OrderPaymentService $payments,
        private readonly OrderRefundService $refunds,
    ) {}

    /** The winner's invoice; called by CloseLot (and offerToNext) inside the lot's lock. */
    public function invoice(AuctionLot $lot): ServiceOrder
    {
        $lot->loadMissing('auction');
        $bidder = AuctionBidder::query()->with('customer')->findOrFail($lot->winner_bidder_id);
        $hammer = Money::baisa($lot->hammer_price);
        $costs = $lot->buyerCosts($hammer);
        $commission = $lot->sellerCommission($hammer);

        $money = new PriceBreakdown(
            price: $hammer,
            fee: $costs['premium'],
            vatOnPrice: $costs['vat_hammer'],
            vatOnFee: $costs['vat_premium'],
            vatRate: app(OrderPricing::class)->vatRate(),
            currency: SiteSetting::currency()['code'],
            commission: $commission,
        );

        $customer = $bidder->customer;
        $order = ServiceOrder::create([
            'auction_lot_id' => $lot->id,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'customer_email' => $customer?->email,
            'customer_phone' => $customer?->phone,
            'locale' => $bidder->locale,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ] + $money->toOrderAttributes());
        $order->recordEvent('created', __('orders.events.created'), ['auction_lot' => $lot->id]);

        $lot->forceFill([
            'invoice_order_id' => $order->id,
            'payment_due_at' => now()->addDays($lot->auction->payment_days),
            'invoice_reminded_at' => null,
            'payment_overdue_at' => null,
            'payout_amount' => Money::omr($hammer + $costs['vat_hammer'] - $commission),
        ])->save();

        return $order;
    }

    /** Money that came by bank transfer (above the card limit): the invoice is paid like any other. */
    public function recordBankTransfer(ServiceOrder $order, string $reference): void
    {
        if (! $order->isPayable()) {
            throw new AuctionBidException('not_payable');
        }

        $this->payments->applyPaid($order, PaymentGateway::BankTransfer, $reference);
    }

    /**
     * Hourly: a reminder a day before an invoice is due, then the overdue flag (an admin decides what
     * follows: wait, or offer the lot to the next bidder).
     *
     * @return array{reminded: int, overdue: int}
     */
    public function sweep(): array
    {
        $open = fn () => AuctionLot::query()->where('status', 'sold')->whereNotNull('payment_due_at')
            ->whereHas('invoice', fn ($q) => $q->where('status', OrderStatus::PendingPayment->value)->where('payment_status', '!=', PaymentStatus::Paid->value));

        $remind = $open()->whereNull('invoice_reminded_at')->whereBetween('payment_due_at', [now(), now()->addDay()])->get();
        foreach ($remind as $lot) {
            $lot->forceFill(['invoice_reminded_at' => now()])->save();
            SendAuctionMessage::dispatch(SendAuctionMessage::INVOICE_DUE, $lot->winner_bidder_id, $lot->id);
        }

        $overdue = $open()->whereNull('payment_overdue_at')->where('payment_due_at', '<', now())->get();
        foreach ($overdue as $lot) {
            $lot->forceFill(['payment_overdue_at' => now()])->save();
        }

        return ['reminded' => $remind->count(), 'overdue' => $overdue->count()];
    }

    /**
     * The winner did not pay: their invoice is cancelled, their deposit kept and they are blocked in
     * this auction; the lot goes to the next highest bidder at their highest bid (or is not sold).
     */
    public function offerToNext(AuctionLot $lot, Authenticatable $by): ?ServiceOrder
    {
        return DB::transaction(function () use ($lot, $by) {
            $lot = AuctionLot::query()->whereKey($lot->getKey())->lockForUpdate()->firstOrFail();
            $invoice = $lot->invoice;

            if ($lot->status !== 'sold' || ! $invoice?->isPayable()) {
                throw new AuctionBidException('not_payable');
            }

            $this->payments->cancelPending($invoice, 'admin');
            $defaulter = AuctionBidder::query()->findOrFail($lot->winner_bidder_id);
            $defaulter->forceFill([
                'status' => 'blocked',
                'deposit_kept_at' => $defaulter->deposit_kept_at ?? now(),
                'note' => trim(($defaulter->note ? $defaulter->note."\n" : '').__('auctions.admin.defaulted_note', ['lot' => $lot->lot_number])),
            ])->save();

            // the highest bid of everyone still allowed to bid, the defaulter (and earlier ones) excluded
            $next = AuctionBid::query()->valid()->where('auction_lot_id', $lot->id)->where('kind', '!=', 'max')
                ->whereHas('bidder', fn ($q) => $q->where('status', 'approved'))
                ->orderByDesc('amount')->orderBy('id')->first();

            if (! $next) {
                $lot->forceFill(['status' => 'unsold', 'hammer_price' => null, 'winner_bidder_id' => null, 'invoice_order_id' => null, 'payment_due_at' => null, 'payment_overdue_at' => null, 'payout_amount' => null])->save();
                $invoice->recordEvent('note', __('auctions.admin.no_next_bidder'), ['by' => $by->getAuthIdentifier()], public: false);

                return null;
            }

            $lot->forceFill(['hammer_price' => $next->amount, 'winner_bidder_id' => $next->auction_bidder_id])->save();
            $order = $this->invoice($lot);
            $invoice->recordEvent('note', __('auctions.admin.offered_to_next', ['bidder' => $next->bidder->paddle_number]), ['by' => $by->getAuthIdentifier()], public: false);
            app(AuctionNotifier::class)->won($lot);

            return $order;
        });
    }

    /**
     * Where a bidder's deposit stands: none (no paid deposit), held (the sale or their invoice is not
     * done), refund_due, refunded, kept (they did not pay an invoice).
     */
    public function depositState(AuctionBidder $bidder): string
    {
        $order = $bidder->depositOrder;

        if (! $order || ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
            return 'none';
        }
        if ($order->payment_status === PaymentStatus::Refunded || $this->refunds->refundable($order) === 0) {
            return 'refunded';
        }
        if ($bidder->deposit_kept_at) {
            return 'kept';
        }

        $unpaidWins = AuctionLot::query()->where('winner_bidder_id', $bidder->id)->where('status', 'sold')
            ->whereDoesntHave('invoice', fn ($q) => $q->where('payment_status', PaymentStatus::Paid->value))
            ->exists();

        return $unpaidWins || $bidder->auction->phase() !== 'ended' ? 'held' : 'refund_due';
    }

    public function refundDeposit(AuctionBidder $bidder, Authenticatable $by): void
    {
        if ($this->depositState($bidder) !== 'refund_due') {
            throw new AuctionBidException('not_refundable');
        }

        $order = $bidder->depositOrder;
        $this->refunds->record($order, $this->refunds->refundable($order), __('auctions.admin.deposit_refund_reason'), (int) $by->getAuthIdentifier());
    }
}
