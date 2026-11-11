<?php

namespace Tests\Feature;

use App\Filament\Resources\StableResource\Pages\EditStable;
use App\Filament\Resources\StableServiceResource\Pages\EditStableService;
use App\Filament\Stable\Pages\Tenancy\EditStableProfile;
use App\Filament\Stable\Resources\StableOfferings\Pages\CreateStableOffering;
use App\Filament\Stable\Resources\StableOfferings\Pages\EditStableOffering;
use App\Filament\Stable\Resources\StableOfferings\StableOfferingResource;
use App\Models\Stable;
use App\Models\StableService;
use App\Models\User;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** One list of services: the admin's stable services are the offerings' types, and the two stay aligned. */
class StableServiceTypesTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableService $lessons;

    private StableService $arena;

    private StableService $boarding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->makeLocation();
        $this->withoutVite();

        $this->lessons = StableService::create(['en_name' => 'Riding Lessons', 'ar_name' => 'دروس ركوب الخيل', 'is_bookable' => true]);
        $this->arena = StableService::create(['en_name' => 'Arena Rental', 'ar_name' => 'تأجير الميدان', 'is_bookable' => true]);
        $this->boarding = StableService::create(['en_name' => 'Boarding', 'ar_name' => 'الإيواء']);

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        // ticked: lessons (bookable) and boarding (not bookable); arena is bookable but not ticked
        $this->stable->services()->attach([$this->lessons->id, $this->boarding->id]);
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

    private function enterAdmin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function offeringData(int $serviceId): array
    {
        return [
            'stable_service_id' => $serviceId,
            'en_name' => 'Arena hour',
            'ar_name' => 'ساعة في الميدان',
            'price' => 10,
            'duration_minutes' => 60,
            'booking_cutoff_minutes' => 60,
            'capacity' => 1,
            'min_riders' => 1,
            'max_riders' => 1,
        ];
    }

    public function test_the_offering_type_lists_only_the_stables_ticked_bookable_services(): void
    {
        $this->enterStablePanel();

        $this->assertSame([$this->lessons->id => 'Riding Lessons'], StableOfferingResource::serviceOptions());

        // a service not offered (bookable but not ticked, or ticked but not bookable) is refused
        foreach ([$this->arena, $this->boarding] as $service) {
            Livewire::test(CreateStableOffering::class)
                ->fillForm($this->offeringData($service->id))
                ->call('create')
                ->assertHasFormErrors(['stable_service_id']);
        }

        Livewire::test(CreateStableOffering::class)
            ->fillForm($this->offeringData($this->lessons->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->lessons->id, $this->stable->offerings()->sole()->stable_service_id);
    }

    public function test_editing_keeps_the_offerings_own_service_in_the_list(): void
    {
        $offering = $this->makeOffering($this->stable, ['stable_service_id' => $this->arena->id]);
        $this->enterStablePanel();

        $this->assertSame([$this->arena->id, $this->lessons->id], array_keys(StableOfferingResource::serviceOptions($offering)));
    }

    public function test_a_service_an_offering_uses_stays_ticked_bookable_and_undeleted(): void
    {
        $this->makeOffering($this->stable, ['stable_service_id' => $this->lessons->id]);
        $this->enterAdmin();

        // the stable's services: lessons cannot be unticked
        Livewire::test(EditStable::class, ['record' => $this->stable->slug])
            ->fillForm(['services' => [$this->boarding->id]])
            ->call('save')
            ->assertHasFormErrors(['services']);
        $this->assertTrue($this->stable->services()->whereKey($this->lessons->id)->exists());

        Livewire::test(EditStable::class, ['record' => $this->stable->slug])
            ->fillForm(['services' => [$this->lessons->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        // the admin's list: booking cannot be turned off, and it cannot be deleted
        Livewire::test(EditStableService::class, ['record' => $this->lessons->id])
            ->fillForm(['is_bookable' => false])
            ->call('save')
            ->assertHasFormErrors(['is_bookable']);
        $this->assertTrue($this->lessons->refresh()->is_bookable);
        $this->assertFalse($this->lessons->delete());

        // an unused one can
        Livewire::test(EditStableService::class, ['record' => $this->arena->id])
            ->fillForm(['is_bookable' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($this->arena->refresh()->is_bookable);
    }

    public function test_the_booking_search_filters_by_bookable_service(): void
    {
        Carbon::setTestNow('2026-10-04 05:30:00');
        $lesson = $this->makeOffering($this->stable, ['stable_service_id' => $this->lessons->id]);
        $arena = $this->makeOffering($this->stable, ['stable_service_id' => $this->arena->id, 'en_name' => 'Arena hour']);
        foreach ([$lesson, $arena] as $offering) {
            app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        }
        $date = '2026-10-06';

        $this->getJson("/api/v1/bookings/search?date={$date}")
            ->assertOk()
            ->assertJsonPath('types', [
                ['value' => (string) $this->arena->id, 'label' => 'Arena Rental'],
                ['value' => (string) $this->lessons->id, 'label' => 'Riding Lessons'],
            ])
            ->assertJsonCount(2, 'results.0.offerings');

        $this->getJson("/api/v1/bookings/search?date={$date}&type={$this->arena->id}")
            ->assertOk()
            ->assertJsonCount(1, 'results.0.offerings')
            ->assertJsonPath('results.0.offerings.0.offering.name', 'Arena hour')
            ->assertJsonPath('results.0.offerings.0.offering.type', ['value' => (string) $this->arena->id, 'label' => 'Arena Rental']);

        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/bookings/search?date={$date}&type={$this->arena->id}")
            ->assertJsonPath('results.0.offerings.0.offering.type.label', 'تأجير الميدان');

        $this->get(route('bookings.search', ['date' => $date, 'type' => $this->lessons->id]))
            ->assertOk()
            ->assertSee('Beginner lesson')
            ->assertDontSee('Arena hour');
    }

    public function test_the_migration_moves_existing_offerings_to_riding_lessons(): void
    {
        $migration = require database_path('migrations/2026_11_10_100000_link_stable_offerings_to_services.php');
        $migration->down();

        // before: no "Riding Lessons" service yet, and an offering of the old enum type
        DB::table('stable_services')->whereKey($this->lessons->id)->delete();
        DB::table('stable_offerings')->insert(['stable_id' => $this->stable->id, 'type' => 'riding_training', 'en_name' => 'Old lesson', 'ar_name' => 'حصة', 'price' => 10, 'duration_minutes' => 60, 'capacity' => 2]);

        $migration->up();

        $lessons = StableService::query()->where('en_name', 'Riding Lessons')->sole();
        $this->assertTrue($lessons->is_bookable);
        $this->assertSame($lessons->id, $this->stable->offerings()->sole()->stable_service_id);
        $this->assertTrue($this->stable->services()->whereKey($lessons->id)->exists());
        $this->assertFalse(Schema::hasColumn('stable_offerings', 'type'));
    }

    public function test_the_owner_can_untick_a_service_once_its_offerings_are_switched_off(): void
    {
        $offering = $this->makeOffering($this->stable, ['stable_service_id' => $this->lessons->id]);
        $this->enterStablePanel();

        // the owner's own profile page: an active offering keeps its service ticked
        Livewire::test(EditStableProfile::class)
            ->fillForm(['services' => []])
            ->call('save')
            ->assertHasFormErrors(['services']);

        $offering->update(['is_active' => false]);
        Livewire::test(EditStableProfile::class)
            ->fillForm(['services' => []])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(0, $this->stable->services()->count());

        // switching it back on needs a ticked, bookable type
        Livewire::test(EditStableOffering::class, ['record' => $offering->getRouteKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasFormErrors(['stable_service_id']);
        $this->assertFalse($offering->refresh()->is_active);

        $this->stable->services()->attach($this->lessons);
        Livewire::test(EditStableOffering::class, ['record' => $offering->getRouteKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($offering->refresh()->is_active);
    }
}
