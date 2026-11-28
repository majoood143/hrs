<?php

namespace Tests\Feature\Auctions;

use App\Filament\Resources\AuctionLotResource\Pages\CreateAuctionLot;
use App\Filament\Resources\AuctionLotResource\Pages\EditAuctionLot;
use App\Filament\Resources\AuctionLotResource\RelationManagers\BidsRelationManager;
use App\Filament\Resources\AuctionResource\Pages\CreateAuction;
use App\Filament\Resources\AuctionResource\Pages\EditAuction;
use App\Filament\Resources\AuctionResource\Pages\ListAuctions;
use App\Filament\Resources\AuctionResource\RelationManagers\BiddersRelationManager;
use App\Filament\Resources\AuctionResource\RelationManagers\LotsRelationManager;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Customer;
use App\Services\Auctions\Bidding;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** Admin → Auctions: the screens in both languages, bidder approval, phone bids and voiding. */
class AuctionAdminTest extends TestCase
{
    use MakesAuctions;
    use PreparesMobileApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareAuctions();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo($this->opensAt->copy()->addHour());
    }

    public function test_an_auction_is_created_with_its_schedule_and_default_steps(): void
    {
        Livewire::test(CreateAuction::class)
            ->fillForm([
                'en_title' => 'Spring sale', 'ar_title' => 'مزاد الربيع', 'slug' => 'spring-sale', 'status' => 'draft',
                'preview_at' => '2027-03-01 12:00', 'bidding_starts_at' => '2027-03-05 18:00', 'ends_at' => '2027-03-08 21:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $auction = Auction::query()->where('slug', 'spring-sale')->firstOrFail();
        // typed in Oman time, stored in UTC
        $this->assertSame('2027-03-08 17:00:00', $auction->ends_at->format('Y-m-d H:i:s'));
        $this->assertCount(count(Auction::DEFAULT_INCREMENTS), $auction->increments);
    }

    public function test_the_screens_open_in_both_languages(): void
    {
        $lot = $this->lot(['en_name' => 'Bahar Muscat', 'ar_name' => 'بحر مسقط']);
        $this->bidding()->place($lot, $this->bidder(), 1_000_000);

        foreach (['en' => ['Winter sale', 'Bahar Muscat', 'In the catalogue'], 'ar' => ['مزاد الشتاء', 'بحر مسقط', 'في الكتالوج']] as $locale => $seen) {
            App::setLocale($locale);
            Livewire::test(ListAuctions::class)->assertOk()->assertSee($seen[0]);
            Livewire::test(EditAuction::class, ['record' => $lot->auction_id])->assertOk();
            Livewire::test(LotsRelationManager::class, ['ownerRecord' => $lot->auction, 'pageClass' => EditAuction::class])
                ->assertOk()->assertSee($seen[1])->assertSee($seen[2]);
            Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])->assertOk();
            Livewire::test(BidsRelationManager::class, ['ownerRecord' => $lot, 'pageClass' => EditAuctionLot::class])->assertOk();
        }
    }

    public function test_a_lot_is_added_to_an_auction_and_closes_in_its_turn(): void
    {
        $auction = $this->auction();

        Livewire::withQueryParams(['auction' => $auction->id])
            ->test(CreateAuctionLot::class)
            ->assertFormSet(['auction_id' => $auction->id])
            ->fillForm(['lot_number' => 3, 'en_name' => 'Najm', 'ar_name' => 'نجم', 'starting_price' => 800, 'status' => 'ready', 'pedigree' => ['sds' => 'Wafi (SA)']])
            ->call('create')
            ->assertHasNoFormErrors();

        $lot = $auction->lots()->firstOrFail();
        $this->assertTrue($lot->ends_at->equalTo($auction->ends_at->copy()->addSeconds(2 * 120)));
        $this->assertSame('Wafi (SA)', $lot->pedigree['sds']);
    }

    public function test_bidders_are_added_and_approved(): void
    {
        $auction = $this->auction();
        $customer = Customer::query()->create(['phone' => '96891112222', 'name' => 'Salim']);
        $manager = Livewire::test(BiddersRelationManager::class, ['ownerRecord' => $auction, 'pageClass' => EditAuction::class]);

        $manager->callTableAction('create', data: ['customer_id' => $customer->id, 'status' => 'pending'])->assertHasNoTableActionErrors();
        $bidder = $auction->bidders()->firstOrFail();
        $this->assertSame([1, 'pending', null], [$bidder->paddle_number, $bidder->status, $bidder->approved_at]);

        $manager->callTableAction('approve', $bidder);
        $this->assertSame('approved', $bidder->fresh()->status);
        $this->assertNotNull($bidder->fresh()->approved_at);
    }

    public function test_a_phone_bid_follows_the_rules_and_a_bid_can_be_voided(): void
    {
        $lot = $this->lot(['starting_price' => 1000]);
        $bidder = $this->bidder();

        Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])
            ->callAction('phoneBid', ['bidder_id' => $bidder->id, 'amount' => 900])
            ->assertNotified(__('auctions.admin.bid_refused'));
        $this->assertSame(0, $lot->fresh()->bids_count);

        Livewire::test(EditAuctionLot::class, ['record' => $lot->getKey()])
            ->callAction('phoneBid', ['bidder_id' => $bidder->id, 'amount' => 1000, 'max_amount' => 2000])
            ->assertNotified(__('auctions.admin.bid_placed'));
        $this->assertSame('1000.000', $lot->fresh()->current_price);

        $bid = AuctionBid::query()->where('kind', 'bid')->firstOrFail();
        Livewire::test(BidsRelationManager::class, ['ownerRecord' => $lot->fresh(), 'pageClass' => EditAuctionLot::class])
            ->callTableAction('void', $bid, ['reason' => 'Wrong bidder'])
            ->assertNotified(__('auctions.admin.bid_voided'));

        $lot = $lot->fresh();
        $this->assertSame([null, 0, null], [$lot->current_price, $lot->bids_count, $lot->leading_bid_id]);
        $this->assertSame('Wrong bidder', $bid->fresh()->void_reason);
    }

    private function bidding(): Bidding
    {
        return app(Bidding::class);
    }
}
