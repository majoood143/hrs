<?php

namespace Tests\Feature\Auctions;

use App\Filament\Resources\AuctionApplicationResource\Pages\EditAuctionApplication;
use App\Filament\Resources\AuctionApplicationResource\Pages\ListAuctionApplications;
use App\Jobs\SendAuctionMessage;
use App\Models\AuctionApplication;
use App\Models\AuctionLot;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\CloseLot;
use App\Services\Sms\SmsManager;
use Filament\Facades\Filament;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** Sellers: the /auctions/sell form, the admin's approval into a lot or rejection, and the seller's view and messages. */
class SellerApplicationTest extends TestCase
{
    use MakesAuctions;
    use PreparesCustomerSite;

    private Customer $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->prepareAuctions();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        Bus::fake([SendAuctionMessage::class]);
        $this->travelTo($this->opensAt->copy()->subDays(5));
        $this->seller = Customer::query()->create(['phone' => '96891234567', 'name' => 'Salim']);
    }

    public function test_a_signed_in_customer_sends_their_horse_with_photos_and_vet_documents(): void
    {
        $this->get(route('auctions.sell'))->assertRedirect();
        $this->actingAs($this->seller, 'customer')->get(route('auctions.sell'))->assertOk()->assertSee(__('auctions.sell.title'))->assertSee('Salim');

        $this->actingAs($this->seller, 'customer')->post(route('auctions.sell.store'), $this->form(['declare' => null]))
            ->assertSessionHasErrors('declare');

        $this->actingAs($this->seller, 'customer')->post(route('auctions.sell.store'), $this->form())
            ->assertRedirect(route('account.auctions'));

        $application = AuctionApplication::query()->firstOrFail();
        $this->assertSame(['pending', 'Bahar Muscat', 'stallion', '3000.000', 'en'], [$application->status, $application->horse_name, $application->gender, (string) $application->asking_reserve, $application->locale]);
        $this->assertCount(2, $application->photos);
        Storage::disk('public')->assertExists($application->photos[0]);
        Storage::disk('local')->assertExists($application->documents[0]);
        $this->assertSame(['https://www.youtube.com/watch?v=abc'], $application->videos);
        $this->assertNotNull($application->declared_at);

        $this->actingAs($this->seller, 'customer')->get(route('account.auctions'))->assertSee('Bahar Muscat')->assertSee(__('auctions.application_statuses.pending'));
    }

    public function test_a_seller_cannot_flood_the_review_queue(): void
    {
        // (the route also allows only 5 sends a minute)
        foreach (range(1, AuctionApplication::MAX_PENDING) as $i) {
            AuctionApplication::query()->create(['customer_id' => $this->seller->id, 'horse_name' => "Horse {$i}", 'gender' => 'mare', 'owner_name' => 'Salim']);
        }

        $this->actingAs($this->seller, 'customer')->post(route('auctions.sell.store'), $this->form())->assertSessionHasErrors('horse_name');
        $this->assertSame(AuctionApplication::MAX_PENDING, AuctionApplication::query()->count());
    }

    public function test_an_admin_approves_it_into_a_draft_lot_and_the_seller_follows_the_sale(): void
    {
        $this->actingAs($this->seller, 'customer')->post(route('auctions.sell.store'), $this->form());
        $application = AuctionApplication::query()->firstOrFail();
        $auction = $this->auction();
        $this->actingAsAdmin();

        App::setLocale('ar');
        Livewire::test(ListAuctionApplications::class)->assertOk()->assertSee('Bahar Muscat');
        App::setLocale('en');

        Livewire::test(EditAuctionApplication::class, ['record' => $application->getKey()])
            ->assertOk()
            ->callAction('approve', ['auction_id' => $auction->id, 'lot_number' => 1, 'starting_price' => 1000, 'reserve_price' => 2500])
            ->assertHasNoActionErrors();

        $lot = AuctionLot::query()->firstOrFail();
        $this->assertSame(['draft', 'Bahar Muscat', 'Amer (SA)', '2500.000', (int) $this->seller->id], [$lot->status, $lot->en_name, $lot->sire_en, (string) $lot->reserve_price, (int) $lot->seller_customer_id]);
        $this->assertSame($application->fresh()->photos, $lot->photos);
        $this->assertSame(['approved', $lot->id], [$application->fresh()->status, (int) $application->fresh()->auction_lot_id]);
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::APPLICATION_APPROVED);

        // on sale, sold: the seller sees it and is told, and cannot bid on it
        $lot->update(['status' => 'ready']);
        $this->travelTo($this->opensAt->copy()->addHour());
        $buyer = $this->bidder([], $auction);
        $sellerAsBidder = $this->bidder(['customer_id' => $this->seller->id], $auction);
        $this->assertSame('own_lot', rescue(fn () => app(Bidding::class)->place($lot, $sellerAsBidder, 1_000_000), fn ($e) => $e->reason, false));
        app(Bidding::class)->place($lot, $buyer, 3_000_000);
        $this->travelTo($lot->fresh()->ends_at);
        app(CloseLot::class)->due();

        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::SELLER_SOLD && $job->id === $lot->id);
        $this->actingAs($this->seller, 'customer')->get(route('account.auctions'))
            ->assertSee(__('auctions.sell.my_horses'))->assertSee(__('auctions.sell.results.sold'));
    }

    public function test_a_rejected_application_tells_the_seller_why(): void
    {
        $this->actingAs($this->seller, 'customer')->post(route('auctions.sell.store'), $this->form());
        $application = AuctionApplication::query()->firstOrFail();
        $this->actingAsAdmin();

        Livewire::test(EditAuctionApplication::class, ['record' => $application->getKey()])
            ->callAction('reject', ['reason' => 'We need the X-rays first'])
            ->assertNotified(__('auctions.admin.application_rejected'));

        $this->assertSame(['rejected', 'We need the X-rays first'], [$application->fresh()->status, $application->fresh()->reject_reason]);
        Bus::assertDispatched(SendAuctionMessage::class, fn ($job) => $job->type === SendAuctionMessage::APPLICATION_REJECTED && $job->id === $application->id);
        $this->actingAs($this->seller, 'customer')->get(route('account.auctions'))->assertSee('We need the X-rays first');
    }

    public function test_the_seller_messages_carry_the_reason_and_the_payout_in_their_language(): void
    {
        Bus::swap(app(Dispatcher::class));
        $this->useSms('demo');
        $this->seller->update(['locale' => 'ar']);
        $application = AuctionApplication::query()->create(['customer_id' => $this->seller->id, 'locale' => 'ar', 'horse_name' => 'بحر', 'gender' => 'mare', 'owner_name' => 'Salim', 'status' => 'rejected', 'reject_reason' => 'نحتاج الأشعة']);

        (new SendAuctionMessage(SendAuctionMessage::APPLICATION_REJECTED, $application->id))->handle(app(SmsManager::class));

        $this->assertStringContainsString('نحتاج الأشعة', (string) NotificationLog::query()->latest('id')->value('message'));
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_filter($overrides + [
            'horse_name' => 'Bahar Muscat',
            'gender' => 'stallion',
            'born_on' => '2022-03-14',
            'colour' => 'bay',
            'sire' => 'Amer (SA)',
            'dam' => 'Nisaee (FR)',
            'owner_name' => 'Salim',
            'asking_reserve' => '3000',
            'videos' => ['https://www.youtube.com/watch?v=abc', ''],
            'photos' => [UploadedFile::fake()->image('side.jpg', 800, 600), UploadedFile::fake()->image('head.jpg', 800, 600)],
            'documents' => [UploadedFile::fake()->create('vet-report.pdf', 200, 'application/pdf')],
            'declare' => '1',
        ], fn ($value) => $value !== null);
    }

    private function actingAsAdmin(): void
    {
        Gate::before(fn () => true);
        $admin = new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        };
        $admin->id = 1;
        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
}
