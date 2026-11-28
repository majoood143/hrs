<?php

namespace Tests\Feature\Auctions;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\AuctionLotResource\Pages\EditAuctionLot;
use App\Filament\Resources\AuctionResource\Pages\EditAuction;
use App\Filament\Resources\AuctionResource\RelationManagers\BiddersRelationManager;
use App\Jobs\SendAuctionMessage;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\ServiceOrder;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\CloseLot;
use App\Services\Auctions\Settlement;
use App\Services\Payments\OrderPaymentService;
use App\Services\Payments\PaymentGateways;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** After the hammer: the invoice and its money, payment, reminders, the next bidder, deposits, payouts. */
class SettlementTest extends TestCase
{
    use MakesAuctions;
    use PreparesCustomerSite;

    private Authenticatable $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite(['vat.enabled' => true, 'vat.rate' => '5']);
        $this->prepareAuctions();
        $this->withoutVite();
        Bus::fake([SendAuctionMessage::class]);
        $this->travelTo($this->opensAt->copy()->addHour());

        $this->defaultAuction = $this->auction(['buyer_premium_percent' => 5, 'seller_commission_percent' => 5, 'entry_fee' => 50, 'payment_days' => 3, 'deposit_amount' => 100]);
        $this->admin = new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        };
        $this->admin->id = 1;
    }

    public function test_the_close_invoices_the_winner_with_the_premium_the_vat_and_the_sellers_commission(): void
    {
        [$lot, $winner] = $this->soldLot(10_000_000);

        $invoice = $lot->invoice;
        // hammer 10,000 + premium 500 + lot VAT 500 + VAT on the premium 25
        $this->assertSame(['10000.000', '500.000', '500.000', '25.000', '11025.000'], [(string) $invoice->price, (string) $invoice->fee_amount, (string) $invoice->vat_on_price, (string) $invoice->vat_on_fee, (string) $invoice->total]);
        // commission 5% + entry fee 50; the seller gets hammer + its VAT − commission
        $this->assertSame(['550.000', '9950.000'], [(string) $invoice->commission_amount, (string) $lot->payout_amount]);
        $this->assertTrue($invoice->isPayable());
        $this->assertSame((int) $winner->customer_id, (int) $invoice->customer_id);
        $this->assertTrue($lot->payment_due_at->equalTo(now()->addDays(3)));
        $this->assertStringContainsString('Lot 1', $invoice->serviceName());
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::WON && $job->id === $winner->id);

        // days to pay, not the checkout's 30 minutes
        $this->travel(2)->hours();
        $this->artisan('orders:expire-pending');
        $this->assertTrue($invoice->fresh()->isPayable());

        // the winner sees it on the lot page
        $this->actingAs($winner->customer, 'customer')->get($lot->url())->assertSee(route('payment.start', $invoice->order_number), false);
    }

    public function test_a_reminder_goes_a_day_before_and_an_unpaid_invoice_is_flagged_overdue(): void
    {
        [$lot] = $this->soldLot(2_000_000);

        $this->artisan('auctions:invoices');
        Bus::assertNotDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::INVOICE_DUE);

        $this->travelTo($lot->payment_due_at->copy()->subHours(12));
        $this->artisan('auctions:invoices');
        $this->artisan('auctions:invoices');
        $this->assertCount(1, Bus::dispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::INVOICE_DUE));

        $this->travelTo($lot->payment_due_at->copy()->addHour());
        $this->artisan('auctions:invoices');
        $this->assertNotNull($lot->fresh()->payment_overdue_at);
    }

    public function test_an_unpaid_lot_goes_to_the_next_bidder_at_their_highest_bid(): void
    {
        $lot = $this->lot(['starting_price' => 1000]);
        [$first, $second, $third] = [$this->bidder(), $this->bidder(), $this->bidder()];
        $bidding = app(Bidding::class);
        $bidding->place($lot, $third, 1_000_000);
        $bidding->place($lot, $second, 1_200_000);
        $bidding->place($lot, $first, 1_500_000);
        $lot = $this->close($lot);
        $oldInvoice = $lot->invoice;

        $order = app(Settlement::class)->offerToNext($lot, $this->admin);

        $lot = $lot->fresh();
        $this->assertSame([(int) $second->id, '1200.000'], [(int) $lot->winner_bidder_id, (string) $lot->hammer_price]);
        $this->assertSame((int) $order->id, (int) $lot->invoice_order_id);
        $this->assertSame(OrderStatus::Cancelled, $oldInvoice->fresh()->status);
        $this->assertSame('blocked', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->deposit_kept_at);

        // nobody left: not sold
        app(Settlement::class)->offerToNext($lot, $this->admin);
        $this->assertSame((int) $third->id, (int) $lot->fresh()->winner_bidder_id);
        app(Settlement::class)->offerToNext($lot->fresh(), $this->admin);
        $this->assertSame(['unsold', null], [$lot->fresh()->status, $lot->fresh()->winner_bidder_id]);
    }

    public function test_deposits_are_held_until_the_sale_ends_and_the_winners_invoice_is_paid(): void
    {
        [$lot, $winner] = $this->soldLot(2_000_000);
        $loser = $this->bidder();
        $settlement = app(Settlement::class);
        foreach ([$winner, $loser] as $bidder) {
            $this->paidDeposit($bidder);
        }

        $this->assertSame(['held', 'refund_due'], [$settlement->depositState($winner->fresh()), $settlement->depositState($loser->fresh())]);
        $this->assertSame('none', $settlement->depositState($this->bidder()));

        app(OrderPaymentService::class)->applyPaid($lot->invoice, PaymentGateway::Demo, 'demo-inv');
        $this->assertSame('refund_due', $settlement->depositState($winner->fresh()));

        $settlement->refundDeposit($loser->fresh(), $this->admin);
        $this->assertSame('refunded', $settlement->depositState($loser->fresh()));
        $this->assertSame(PaymentStatus::Refunded, $loser->fresh()->depositOrder->payment_status);
    }

    public function test_a_bank_transfer_pays_the_invoice_and_is_never_a_checkout_option(): void
    {
        [$lot] = $this->soldLot(30_000_000);
        $this->seedSiteSettings(['enabled_gateways' => ['demo', 'bank_transfer'], 'vat.enabled' => true, 'vat.rate' => '5']);

        $this->assertNotContains(PaymentGateway::BankTransfer, app(PaymentGateways::class)->selected());

        app(Settlement::class)->recordBankTransfer($lot->invoice, 'FT123');
        $invoice = $lot->invoice->fresh();
        $this->assertSame([PaymentStatus::Paid, PaymentGateway::BankTransfer, 'FT123'], [$invoice->payment_status, $invoice->payment_method, $invoice->payment_reference]);
        $this->assertNotNull($invoice->receipt_number);
    }

    public function test_the_admin_records_the_transfer_the_hand_over_the_payout_and_the_deposit_refund(): void
    {
        [$lot] = $this->soldLot(2_000_000);
        $loser = $this->bidder();
        $this->paidDeposit($loser);

        Gate::before(fn () => true);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])->assertOk()->assertSee(__('auctions.admin.tabs.settlement'));
        $page->callAction('bankTransfer', ['reference' => 'FT555'])->assertNotified(__('auctions.admin.invoice_paid'));
        Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])->callAction('handedOver');
        Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])->callAction('sellerPaid', ['payout_reference' => 'PAY-1']);

        $lot = $lot->fresh();
        $this->assertTrue($lot->invoice->isPaid());
        $this->assertNotNull($lot->handed_over_at);
        $this->assertSame('PAY-1', $lot->payout_reference);

        Livewire::test(BiddersRelationManager::class, ['ownerRecord' => $lot->auction, 'pageClass' => EditAuction::class])
            ->assertOk()
            ->callTableAction('refundDeposit', $loser)
            ->assertNotified(__('auctions.admin.deposit_refunded'));
        $this->assertSame(PaymentStatus::Refunded, $loser->fresh()->depositOrder->payment_status);
    }

    /** @return array{0: AuctionLot, 1: AuctionBidder} a lot sold (and invoiced) to a fresh bidder, every lot of the auction closed */
    private function soldLot(int $hammer): array
    {
        $lot = $this->lot(['starting_price' => $hammer / 1000, 'vat_rate' => 5]);
        $winner = $this->bidder();
        app(Bidding::class)->place($lot, $winner, $hammer);

        return [$this->close($lot), $winner];
    }

    private function close(AuctionLot $lot): AuctionLot
    {
        $this->travelTo($lot->fresh()->ends_at);
        app(CloseLot::class)->due();

        return $lot->fresh();
    }

    private function paidDeposit(AuctionBidder $bidder): void
    {
        $order = ServiceOrder::create([
            'auction_bidder_id' => $bidder->id, 'customer_id' => $bidder->customer_id, 'customer_phone' => '968', 'locale' => 'en',
            'status' => OrderStatus::PendingPayment, 'payment_status' => PaymentStatus::Pending,
            'currency' => 'OMR', 'price' => '100.000', 'fee_amount' => '0', 'vat_rate' => 0, 'vat_on_price' => '0', 'vat_on_fee' => '0', 'total' => '100.000',
        ]);
        $bidder->update(['deposit_order_id' => $order->id]);
        app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Demo, 'dep-'.$bidder->id);
    }
}
