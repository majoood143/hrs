<?php

namespace Tests\Feature;

use App\Enums\StableApprovalStatus;
use App\Filament\Stable\Widgets\StableStatsOverview;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableClosure;
use App\Models\User;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Every owner screen through real requests (the panel's own middleware, tenancy and policies). */
class StableOwnerPanelPagesTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, status: StableApprovalStatus::Pending);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(string $path = ''): string
    {
        return '/stable/'.$this->stable->slug.$path;
    }

    public function test_guests_are_sent_to_the_login_and_the_auth_pages_render(): void
    {
        $this->get($this->url())->assertRedirect('/stable/login');
        $this->get('/stable/login')->assertOk()->assertSee('Stable owners: sign in');
        $this->get('/stable/register?lang=ar')->assertOk()->assertSee('سجّل إسطبلك');
    }

    public function test_every_owner_screen_renders(): void
    {
        $offering = $this->makeOffering($this->stable, ['en_name' => 'Pony ride']);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        $this->book(BookingSlot::query()->firstOrFail(), 2);
        StableClosure::create(['stable_id' => $this->stable->id, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21', 'reason' => 'National Day']);

        $this->actingAs($this->owner);

        $this->get($this->url())->assertOk()->assertSee('Waiting for approval');
        $this->get($this->url('/stable-offerings'))->assertOk()->assertSee('Pony ride');
        $this->get($this->url('/stable-offerings/create'))->assertOk();
        $this->get($this->url('/stable-offerings/'.$offering->id.'/edit'))->assertOk();
        $this->get($this->url('/stable-schedules'))->assertOk()->assertSee('10:00');
        $this->get($this->url('/stable-schedules/create'))->assertOk();
        $this->get($this->url('/booking-slots'))->assertOk()->assertSee('2 / 4');
        $this->get($this->url('/stable-closures'))->assertOk()->assertSee('National Day');
        $this->get($this->url('/booking-settings'))->assertOk();
        $this->get($this->url('/profile'))->assertOk();
        $this->get('/stable/new')->assertOk();
    }

    public function test_the_week_stats_count_places_and_riders(): void
    {
        $offering = $this->makeOffering($this->stable);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        $this->book(BookingSlot::query()->where('date', '2026-10-06')->sole(), 3);

        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();

        // this week: one Tuesday slot of 4 places, 3 of them booked
        Livewire::test(StableStatsOverview::class)
            ->assertSee('Riders booked')
            ->assertSee('75% full, next 7 days');
    }

    public function test_staff_screens_appear_only_when_switched_on(): void
    {
        $this->actingAs($this->owner);
        $this->get($this->url('/stable-trainers'))->assertForbidden();

        $this->stable->forceFill(['booking_settings' => ['staff_enabled' => true]])->save();

        $this->get($this->url('/stable-trainers'))->assertOk();
        $this->get($this->url('/stable-horses'))->assertOk();
    }

    public function test_an_owner_cannot_open_another_stable_or_the_admin(): void
    {
        $other = $this->makeStable($this->makeOwner('other@example.com', '96899999999'), ['slug' => 'other-stable']);
        $theirOffering = $this->makeOffering($other);

        $this->actingAs($this->owner);

        $this->get('/stable/other-stable')->assertNotFound();
        $this->get($this->url('/stable-offerings/'.$theirOffering->id.'/edit'))->assertNotFound();
        $this->get('/admin')->assertForbidden();
    }

    public function test_an_approved_stable_shows_its_commission_and_public_link(): void
    {
        $this->stable->forceFill(['approval_status' => StableApprovalStatus::Approved, 'commission_type' => 'percentage', 'commission_value' => 10])->save();

        $this->actingAs($this->owner)
            ->get($this->url())
            ->assertOk()
            ->assertSee('10%')
            ->assertSee(route('stables.show', $this->stable->slug), false);
    }
}
