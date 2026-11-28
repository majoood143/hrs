<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendAuctionMessage;
use App\Models\AppEvent;
use App\Models\AuctionBidder;
use App\Models\AuctionWatch;
use App\Models\Customer;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\CloseLot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** /api/v1/auctions: the app's auctions, in the request's language, with the website's rules. */
class AuctionsTest extends TestCase
{
    use MakesAuctions;
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->prepareAuctions();
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        Storage::fake('local');
        Bus::fake([SendAuctionMessage::class]);
        $this->travelTo($this->opensAt->copy()->addHour());
    }

    public function test_auctions_and_lots_are_read_in_the_request_language_without_the_reserve(): void
    {
        $lot = $this->lot(['en_name' => 'Bahar', 'ar_name' => 'بحر', 'gender' => 'stallion', 'reserve_price' => 4321, 'sire_en' => 'Amer (SA)', 'pedigree' => ['sd' => 'Reem (OM)'], 'documents' => ['auctions/documents/xray.pdf']]);
        $this->auction(['slug' => 'hidden-sale', 'status' => 'draft']);

        $this->getJson('/api/v1/auctions')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'winter-sale')->assertJsonPath('data.0.phase', 'live')->assertJsonPath('data.0.lots_count', 1);
        $this->getJson('/api/v1/auctions/hidden-sale')->assertNotFound();

        $this->getJson('/api/v1/auctions/winter-sale', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.title', 'مزاد الشتاء')
            ->assertJsonPath('data.lots.0.name', 'بحر')
            ->assertJsonPath('data.lots.0.gender.label', 'فحل')
            ->assertJsonPath('data.me', null);

        $response = $this->getJson('/api/v1/auctions/winter-sale/lots/1')->assertOk()
            ->assertJsonPath('data.pedigree.s', 'Amer (SA)')
            ->assertJsonPath('data.pedigree.sd', 'Reem (OM)')
            ->assertJsonPath('data.documents', [])
            ->assertJsonPath('data.documents_locked', true)
            ->assertJsonPath('data.state.minimum.amount', '1000.000')
            ->assertJsonPath('data.state.you', null);
        $this->assertStringNotContainsString('4321', $response->getContent());
        $this->assertNotNull($response->json('data.state.server_now'));
        $this->getJson('/api/v1/auctions/winter-sale/lots/9')->assertNotFound();
        $this->assertSame(1, $lot->lot_number);
    }

    public function test_a_customer_registers_bids_and_watches_through_the_api(): void
    {
        $this->lot(['starting_price' => 1000]);
        $this->auction(['slug' => 'other-sale']);
        $this->defaultAuction->update(['auto_approve' => true]);
        $customer = Customer::query()->create(['phone' => '96891234567', 'name' => null]);
        $token = $customer->createToken('app')->plainTextToken;

        $this->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1000])->assertUnauthorized();
        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1000])
            ->assertUnprocessable()->assertJsonPath('code', 'not_registered')->assertJsonPath('data.minimum.amount', '1000.000');

        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/register', ['national_id' => 'A1234567'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'accept']);
        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/register', ['name' => 'Salim', 'national_id' => 'A1234567', 'accept' => true])
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.deposit_pay_url', null);
        $this->assertTrue(AppEvent::query()->where('name', 'auction_registered')->where('customer_id', $customer->id)->exists());

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auctions/winter-sale')->assertJsonPath('data.me.status', 'approved');

        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1000, 'max_amount' => 2000])
            ->assertOk()->assertJsonPath('data.you', 'leading')->assertJsonPath('data.your_max.amount', '2000.000')
            ->assertJsonPath('message', __('auctions.site.bid_leading'));
        $this->assertTrue(AuctionWatch::query()->where('customer_id', $customer->id)->exists());

        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1, 'max_amount' => 0.5])->assertUnprocessable();

        $this->forgetGuards();
        $this->withToken($token)->deleteJson('/api/v1/auctions/winter-sale/lots/1/watch')->assertOk()->assertJsonPath('data.watching', false);
        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/account/auctions')->assertOk()
            ->assertJsonPath('data.bids.0.you', 'leading')->assertJsonPath('data.registrations.0.status', 'approved');
    }

    public function test_errors_are_in_arabic_and_the_feature_switch_closes_bidding(): void
    {
        $this->lot();
        $bidder = $this->bidder();
        $token = $bidder->customer->createToken('app')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()->assertJsonPath('code', 'too_low')->assertJsonPath('message', __('auctions.errors.too_low', ['minimum' => '1000.000'], 'ar'));

        $this->seedSiteSettings(['mobile.features' => json_encode(['auctions' => ['enabled' => false]])]);
        $this->forgetGuards();
        $response = $this->withToken($token)->postJson('/api/v1/auctions/winter-sale/lots/1/bids', ['amount' => 1000]);
        $this->assertContains($response->status(), [403, 404, 503]);
        $this->getJson('/api/v1/auctions/winter-sale')->assertOk();
    }

    public function test_approved_bidders_get_signed_links_to_the_vet_documents(): void
    {
        Storage::disk('local')->put('auctions/documents/xray.pdf', '%PDF-1.4 x-ray');
        $this->lot(['documents' => ['auctions/documents/xray.pdf']]);
        $token = $this->bidder()->customer->createToken('app')->plainTextToken;

        $url = $this->withToken($token)->getJson('/api/v1/auctions/winter-sale/lots/1')->assertJsonPath('data.documents_locked', false)->json('data.documents.0.url');

        $this->get($url)->assertOk();
        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_the_winner_finds_the_checkout_of_their_invoice(): void
    {
        $lot = $this->lot();
        $winner = $this->bidder();
        app(Bidding::class)->place($lot, $winner, 1_000_000);
        $this->travelTo($lot->fresh()->ends_at);
        app(CloseLot::class)->due();

        $token = AuctionBidder::query()->find($winner->id)->customer->createToken('app')->plainTextToken;
        $state = $this->withToken($token)->getJson('/api/v1/auctions/winter-sale/lots/1/state')->assertOk()
            ->assertJsonPath('data.status', 'sold')->assertJsonPath('data.you', 'won')->json('data');

        $this->assertStringContainsString('/pay/', $state['invoice']['pay_url']);
        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/account/auctions')->assertJsonPath('data.bids.0.you', 'won')
            ->assertJsonPath('data.bids.0.pay_url', $state['invoice']['pay_url']);
        $this->assertTrue(AppEvent::query()->where('name', 'lot_won')->exists());
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
