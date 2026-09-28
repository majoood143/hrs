<?php

namespace Tests\Feature;

use App\Enums\StableBookingStatus;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Filament\Resources\StableResource\RelationManagers\PaymentAccountsRelationManager;
use App\Filament\Stable\Pages\BookingSettings;
use App\Filament\Stable\Resources\StableBookings\Pages\ListStableBookings;
use App\Jobs\SendStableEmail;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StablePaymentAccount;
use App\Models\User;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** The owner's bookings screen and gateway keys, and the admins' review of those keys. */
class StableBookingPanelsTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo']);
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        $offering = $this->makeOffering($this->stable, ['price' => 0]);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enterStablePanel(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();
    }

    private function booking(): StableBooking
    {
        return app(CreateStableBooking::class)->handle($this->slot, [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']], ['name' => 'Aisha', 'phone' => '91234567']);
    }

    public function test_the_owner_sees_and_cancels_bookings(): void
    {
        $booking = $this->booking();
        $this->enterStablePanel();

        Livewire::test(ListStableBookings::class)
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertSee('Aisha')
            ->assertSee('Omar')
            ->callTableAction('cancelBooking', $booking, ['reason' => 'A storm is coming.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame([StableBookingStatus::Cancelled, 'stable'], [$booking->refresh()->status, $booking->cancellation_source]);
    }

    public function test_the_bookings_page_renders_through_the_panel(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->owner)
            ->get('/stable/'.$this->stable->slug.'/stable-bookings')
            ->assertOk()
            ->assertSee($booking->reference);
    }

    public function test_the_owner_saves_their_own_gateway_keys_for_approval(): void
    {
        Queue::fake();
        Http::swap(new Factory);
        // a good key: Thawani answers "not found" for the made-up session, not "unauthorised"
        Http::fake(['*checkout/session/*' => Http::response(['success' => false], 404)]);
        $this->superAdminFor();
        $this->enterStablePanel();

        Livewire::test(BookingSettings::class)
            ->fillForm([
                'payment_mode' => 'own',
                'payment_gateway' => 'thawani',
                'accounts.thawani.test_mode' => true,
                'accounts.thawani.credentials.secret_key' => 'sk_test_123',
                'accounts.thawani.credentials.publishable_key' => 'pk_test_123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $account = StablePaymentAccount::query()->sole();
        $this->assertSame([StablePaymentAccount::PENDING, 'sk_test_123', true], [$account->status, $account->get('secret_key'), $account->last_test_ok]);
        $this->assertSame('own', $this->stable->refresh()->bookingSettings()->paymentMode());
        // not approved yet: we still collect
        $this->assertNull($this->stable->activePaymentAccount());
        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->type === 'stable_keys_submitted');

        // saving again with the secret left empty keeps it
        Livewire::test(BookingSettings::class)
            ->assertFormSet(['accounts.thawani.credentials.secret_key' => '', 'accounts.thawani.credentials.publishable_key' => 'pk_test_123'])
            ->call('save');
        $this->assertSame('sk_test_123', $account->refresh()->get('secret_key'));
    }

    public function test_a_bad_key_is_reported_to_the_owner(): void
    {
        Http::swap(new Factory);
        Http::fake(['*checkout/session/*' => Http::response(['message' => 'unauthorized'], 401)]);
        $this->enterStablePanel();

        Livewire::test(BookingSettings::class)
            ->fillForm([
                'payment_mode' => 'own',
                'payment_gateway' => 'thawani',
                'accounts.thawani.credentials.secret_key' => 'sk_wrong',
                'accounts.thawani.credentials.publishable_key' => 'pk',
            ])
            ->call('save');

        $this->assertFalse(StablePaymentAccount::query()->sole()->last_test_ok);
    }

    public function test_an_admin_reviews_the_keys(): void
    {
        Queue::fake();
        $account = StablePaymentAccount::create([
            'stable_id' => $this->stable->id,
            'gateway' => 'nbo',
            'credentials' => ['tranportal_id' => 'T1', 'tranportal_password' => 'secret-pass', 'resource_key' => str_repeat('k', 32)],
            'test_mode' => false,
        ]);
        $this->stable->forceFill(['booking_settings' => ['payment_mode' => 'own', 'payment_gateway' => 'nbo']])->save();

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PaymentAccountsRelationManager::class, ['ownerRecord' => $this->stable, 'pageClass' => ViewStable::class])
            ->assertOk()
            ->assertSee('NBO')
            ->assertDontSee('secret-pass')
            ->callTableAction('approve', $account)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($account->refresh()->isApproved());
        $this->assertSame($account->id, $this->stable->refresh()->activePaymentAccount()?->id);
        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->to === 'owner@example.com' && $job->type === 'stable_keys_approved');
    }

    public function test_the_admin_order_page_shows_the_booking(): void
    {
        $booking = $this->booking();

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewServiceOrder::class, ['record' => $booking->service_order_id])
            ->assertOk()
            ->assertSee('Stable booking')
            ->assertSee($booking->reference)
            ->assertSee('Beginner lesson · Desert Riders');
    }

    private function superAdminFor(): void
    {
        $admin = $this->makeAdminUser('boss@example.com');
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    }
}
