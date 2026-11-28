<?php

namespace Tests\Feature\Auctions;

use App\Enums\PaymentGateway;
use App\Jobs\SendAuctionMessage;
use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\AuctionWatch;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Services\Auctions\Bidding;
use App\Services\Payments\OrderPaymentService;
use App\Services\Sms\SmsManager;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** The public auction pages: catalogue, lot page, registration with a deposit, bids, documents, messages. */
class AuctionWebsiteTest extends TestCase
{
    use MakesAuctions;
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->prepareAuctions();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        Bus::fake([SendAuctionMessage::class]);
        $this->travelTo($this->opensAt->copy()->addHour());
    }

    public function test_only_published_auctions_and_catalogue_lots_are_shown_and_the_reserve_stays_hidden(): void
    {
        $lot = $this->lot(['en_name' => 'Bahar Muscat', 'reserve_price' => 4321, 'sire_en' => 'Amer (SA)', 'pedigree' => ['ssss' => 'Deep Ancestor'], 'documents' => ['auctions/documents/xray.pdf']]);
        $this->lot(['en_name' => 'Draft Horse', 'status' => 'draft']);
        $this->auction(['slug' => 'secret-sale', 'en_title' => 'Secret sale', 'status' => 'draft']);
        $this->auction(['slug' => 'later-sale', 'en_title' => 'Later sale', 'preview_at' => now()->addDay()]);

        $this->get(route('auctions.index'))->assertOk()->assertSee('Winter sale')->assertDontSee('Secret sale')->assertDontSee('Later sale');
        $this->get(route('auctions.show', 'secret-sale'))->assertNotFound();

        $this->get(route('auctions.show', 'winter-sale'))->assertOk()->assertSee('Bahar Muscat')->assertDontSee('Draft Horse')->assertDontSee('4,321');

        $this->get($lot->url())
            ->assertOk()
            ->assertSee('Amer (SA)')
            ->assertSee('Deep Ancestor')
            ->assertSee(__('auctions.site.documents_locked'));

        $state = $this->getJson(route('auctions.lot.state', ['auction' => 'winter-sale', 'lot' => $lot->lot_number]))->assertOk();
        $this->assertArrayNotHasKey('reserve_price', $state->json());
        $this->assertStringNotContainsString('4321', $state->getContent());
        $this->assertNotNull($state->json('server_now'));
    }

    public function test_registering_without_a_deposit_on_an_auto_approved_auction_lets_them_bid_at_once(): void
    {
        $this->lot();
        $this->defaultAuction()->update(['auto_approve' => true]);
        $customer = $this->customer(['name' => null]);

        $this->actingAs($customer, 'customer')
            ->post(route('auctions.register.store', 'winter-sale'), ['name' => 'Salim', 'national_id' => 'A1234567', 'accept' => '1'])
            ->assertRedirect(route('auctions.show', 'winter-sale'));

        $bidder = AuctionBidder::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('approved', $bidder->status);
        $this->assertSame('A1234567', $customer->fresh()->national_id);
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::APPROVED && $job->id === $bidder->id);
    }

    public function test_the_conditions_must_be_accepted_and_without_auto_approval_an_admin_decides(): void
    {
        $this->lot();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')->post(route('auctions.register.store', 'winter-sale'), ['national_id' => 'A1234567'])->assertSessionHasErrors('accept');
        $this->actingAs($customer, 'customer')->post(route('auctions.register.store', 'winter-sale'), ['national_id' => 'A1234567', 'accept' => '1']);

        $this->assertSame('pending', AuctionBidder::query()->where('customer_id', $customer->id)->value('status'));
        Bus::assertNotDispatched(SendAuctionMessage::class);
    }

    public function test_a_deposit_is_paid_through_the_checkout_and_approves_the_bidder(): void
    {
        $this->lot();
        $this->defaultAuction()->update(['auto_approve' => true, 'deposit_amount' => 100]);
        $customer = $this->customer();

        $response = $this->actingAs($customer, 'customer')->post(route('auctions.register.store', 'winter-sale'), ['national_id' => 'A1234567', 'accept' => '1']);

        $bidder = AuctionBidder::query()->where('customer_id', $customer->id)->firstOrFail();
        $order = ServiceOrder::query()->findOrFail($bidder->deposit_order_id);
        $response->assertRedirect(route('payment.start', $order->order_number));
        $this->assertSame(['pending', '100.000', '0.000', $bidder->id], [$bidder->status, (string) $order->total, (string) $order->fee_amount, (int) $order->auction_bidder_id]);
        $this->assertStringContainsString('Winter sale', $order->serviceName());

        // registering again sends them to the same open checkout
        $this->actingAs($customer, 'customer')->post(route('auctions.register.store', 'winter-sale'), ['accept' => '1'])->assertRedirect(route('payment.start', $order->order_number));
        $this->assertSame(1, ServiceOrder::query()->count());

        app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Demo, 'demo-1');

        $this->assertSame('approved', $bidder->fresh()->status);
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::APPROVED);
    }

    public function test_bids_from_the_website_follow_the_rules_and_tell_the_outbid_bidder(): void
    {
        $lot = $this->lot(['starting_price' => 1000]);
        $url = route('auctions.bid', ['auction' => 'winter-sale', 'lot' => $lot->lot_number]);
        $first = $this->bidder();
        $second = $this->bidder();
        $pending = $this->bidder(['status' => 'pending']);

        $this->postJson($url, ['amount' => 1000])->assertUnauthorized();
        $this->actingAs($pending->customer, 'customer')->postJson($url, ['amount' => 1000])->assertUnprocessable()->assertJsonPath('reason', 'not_approved');
        $this->actingAs($this->customer(), 'customer')->postJson($url, ['amount' => 1000])->assertUnprocessable()->assertJsonPath('reason', 'not_registered');

        $this->actingAs($first->customer, 'customer')->postJson($url, ['amount' => 1000, 'max_amount' => 1500])
            ->assertOk()->assertJsonPath('state.you', 'leading')->assertJsonPath('message', __('auctions.site.bid_leading'));
        $this->assertTrue(AuctionWatch::query()->where('customer_id', $first->customer_id)->where('auction_lot_id', $lot->id)->exists());

        $this->actingAs($second->customer, 'customer')->postJson($url, ['amount' => 1100])
            ->assertOk()->assertJsonPath('state.you', 'outbid')->assertJsonPath('message', __('auctions.site.bid_outbid_at_once'));
        $this->actingAs($second->customer, 'customer')->postJson($url, ['amount' => 1100])->assertUnprocessable()->assertJsonPath('reason', 'too_low');

        $state = $this->actingAs($second->customer, 'customer')->postJson($url, ['amount' => 1700])->assertOk()->json('state');
        $this->assertSame(['leading', 3], [$state['you'], $state['bidders'] + 1]);
        $this->assertStringNotContainsString('1500', json_encode($state['history']));
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::OUTBID && $job->id === $first->id);

        $this->actingAs($first->customer, 'customer')->getJson(route('auctions.lot.state', ['auction' => 'winter-sale', 'lot' => $lot->lot_number]))
            ->assertJsonPath('you', 'outbid')->assertJsonPath('your_max', null);
    }

    public function test_vet_documents_are_for_approved_bidders_only(): void
    {
        Storage::disk('local')->put('auctions/documents/xray.pdf', '%PDF-1.4 x-ray');
        $lot = $this->lot(['documents' => ['auctions/documents/xray.pdf']]);
        $url = route('auctions.document', ['auction' => 'winter-sale', 'lot' => $lot->lot_number, 'index' => 0]);

        $this->get($url)->assertRedirect();
        $this->actingAs($this->bidder(['status' => 'pending'])->customer, 'customer')->get($url)->assertForbidden();
        $this->actingAs($this->bidder()->customer, 'customer')->get($url)->assertOk();
        $this->actingAs($this->bidder()->customer, 'customer')->get(route('auctions.document', ['auction' => 'winter-sale', 'lot' => $lot->lot_number, 'index' => 5]))->assertNotFound();
    }

    public function test_a_lot_past_its_time_is_closed_when_read_and_the_winner_is_told(): void
    {
        $lot = $this->lot();
        $winner = $this->bidder();
        app(Bidding::class)->place($lot, $winner, 1_000_000);

        $this->travelTo($lot->fresh()->ends_at->addSecond());
        $this->actingAs($winner->customer, 'customer')
            ->getJson(route('auctions.lot.state', ['auction' => 'winter-sale', 'lot' => $lot->lot_number]))
            ->assertJsonPath('status', 'sold')->assertJsonPath('you', 'won')->assertJsonPath('open', false);

        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::WON && $job->id === $winner->id);
        $this->actingAs($winner->customer, 'customer')->get(route('account.auctions'))->assertOk()->assertSee(__('auctions.site.you_states.won'));
    }

    public function test_watchers_are_told_once_when_a_lot_closes_soon(): void
    {
        $lot = $this->lot();
        AuctionWatch::query()->create(['customer_id' => $this->customer()->id, 'auction_lot_id' => $lot->id]);

        $this->artisan('auctions:remind-ending')->assertSuccessful();
        Bus::assertNotDispatched(SendAuctionMessage::class);

        $this->travelTo($lot->ends_at->copy()->subMinutes(10));
        $this->artisan('auctions:remind-ending');
        $this->artisan('auctions:remind-ending');
        Bus::assertDispatchedTimes(SendAuctionMessage::class, 1);
    }

    public function test_the_messages_are_written_in_the_bidders_language(): void
    {
        Bus::swap(app(Dispatcher::class));
        $this->useSms('demo');
        $lot = $this->lot(['ar_name' => 'بحر']);
        $bidder = $this->bidder(['locale' => 'ar']);
        app(Bidding::class)->place($lot, $bidder, 1_000_000);

        (new SendAuctionMessage(SendAuctionMessage::OUTBID, $bidder->id, $lot->id))->handle(app(SmsManager::class));

        $this->assertDatabaseHas('notification_logs', ['type' => 'auction_outbid']);
        $this->assertStringContainsString('بحر', (string) NotificationLog::query()->latest('id')->value('message'));
    }

    private function customer(array $attributes = []): Customer
    {
        static $phone = 96891000000;

        return Customer::query()->create($attributes + ['phone' => (string) $phone++, 'name' => 'Visitor']);
    }

    private function defaultAuction(): Auction
    {
        return $this->defaultAuction ??= $this->auction();
    }
}
