<?php

namespace Tests\Feature;

use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Filament\Stable\Pages\ChangeLog;
use App\Filament\Stable\Resources\StableOfferings\Pages\EditStableOffering;
use App\Filament\Widgets\Stables\StableChangeLogWidget;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Admins working in a stable's own panel on its owner's behalf, and the stable's change log. */
class StableAdminModeTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        $this->offering = $this->makeOffering($this->stable);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $admin = $this->makeAdminUser('boss@example.com');
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function stableEditor(): User
    {
        $admin = $this->makeAdminUser('editor@example.com');
        $admin->givePermissionTo(Permission::findOrCreate('Update:Stable', 'web'));

        return $admin;
    }

    private function enter(User $user, Stable $stable): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($stable);
        Filament::bootCurrentPanel();
    }

    public function test_who_may_manage_stables(): void
    {
        $this->assertTrue($this->superAdmin()->managesStables());
        $this->assertTrue($this->stableEditor()->managesStables());
        $this->assertFalse($this->makeAdminUser('clerk@example.com')->managesStables());
        $this->assertFalse($this->owner->managesStables());

        $editor = User::query()->where('email', 'editor@example.com')->sole();
        $other = $this->makeStable(null, ['slug' => 'other-stable']);
        $panel = Filament::getPanel('stable');

        $this->assertTrue($editor->canAccessPanel($panel));
        $this->assertTrue($editor->canAccessTenant($other));
        $this->assertEqualsCanonicalizing([$this->stable->id, $other->id], $editor->getTenants($panel)->pluck('id')->all());
        // an owner still sees only their own
        $this->assertSame([$this->stable->id], $this->owner->getTenants($panel)->pluck('id')->all());
        $this->assertFalse($this->owner->canAccessTenant($other));
    }

    public function test_an_admin_opens_any_stable_under_a_warning_and_the_visit_is_logged(): void
    {
        $admin = $this->stableEditor();

        $this->actingAs($admin)
            ->get('/stable/'.$this->stable->slug)
            ->assertOk()
            ->assertSee('You are managing Desert Riders as an admin.');

        // logged once per session
        $this->actingAs($admin)->get('/stable/'.$this->stable->slug.'/stable-offerings')->assertOk();
        $opened = Activity::query()->where('event', 'admin_opened')->sole();
        $this->assertSame([$admin->id, true, $this->stable->id], [$opened->causer_id, $opened->getExtraProperty('as_admin'), $opened->getExtraProperty('stable_id')]);

        // the owner sees no warning, and nothing is logged for them (a fresh session: Filament
        // signs a session out when its user changes)
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner)
            ->get('/stable/'.$this->stable->slug)
            ->assertOk()
            ->assertDontSee('as an admin');
        $this->assertSame(1, Activity::query()->where('event', 'admin_opened')->count());
    }

    public function test_an_admin_without_the_permission_is_kept_out(): void
    {
        $this->actingAs($this->makeAdminUser('clerk@example.com'))
            ->get('/stable/'.$this->stable->slug)
            ->assertForbidden();
    }

    public function test_admins_do_not_register_stables_from_the_owner_panel(): void
    {
        $status = $this->actingAs($this->superAdmin())->get('/stable/new')->status();

        $this->assertContains($status, [403, 404]);
    }

    public function test_changes_are_logged_with_who_made_them(): void
    {
        $admin = $this->superAdmin();
        $this->enter($admin, $this->stable);

        Livewire::test(EditStableOffering::class, ['record' => $this->offering->getRouteKey()])
            ->fillForm(['price' => 18])
            ->call('save')
            ->assertHasNoFormErrors();

        $change = Activity::query()->where('subject_type', StableOffering::class)->where('event', 'updated')->sole();
        $this->assertSame($admin->id, $change->causer_id);
        $this->assertTrue($change->getExtraProperty('as_admin'));
        $this->assertSame($this->stable->id, $change->getExtraProperty('stable_id'));
        $this->assertSame(['15.000', '18.000'], [$change->properties['old']['price'], $change->properties['attributes']['price']]);

        // the owner's own change is not an admin one
        $this->enter($this->owner, $this->stable);
        Livewire::test(EditStableOffering::class, ['record' => $this->offering->getRouteKey()])
            ->fillForm(['price' => 20])
            ->call('save');
        $this->assertFalse(Activity::query()->where('causer_id', $this->owner->id)->where('event', 'updated')->sole()->getExtraProperty('as_admin'));
    }

    public function test_the_slot_generator_does_not_flood_the_log(): void
    {
        $this->enter($this->owner, $this->stable);
        $schedule = $this->makeSchedule($this->offering, [0, 1, 2, 3, 4, 5, 6], ['08:00', '10:00', '16:00']);
        $before = Activity::query()->count();

        app(SlotGenerator::class)->sync($schedule, refresh: true);

        $this->assertSame($before, Activity::query()->count());
    }

    public function test_both_sides_see_the_change_log(): void
    {
        $admin = $this->superAdmin();
        $this->enter($admin, $this->stable);
        Livewire::test(EditStableOffering::class, ['record' => $this->offering->getRouteKey()])->fillForm(['price' => 18])->call('save');

        // the owner, in their panel
        $this->enter($this->owner, $this->stable);
        Livewire::test(ChangeLog::class)
            ->assertOk()
            ->assertSee('Service changed')
            ->assertSee('Admin, on the stable')
            ->assertSee('15.000 → 18.000');

        // the admins, on the stable's page
        Gate::before(fn () => true);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewStable::class, ['record' => $this->stable->slug])->assertOk()->assertSee('Open stable panel');
        Livewire::test(StableChangeLogWidget::class, ['record' => $this->stable])->assertOk()->assertSee('Service changed');
    }

    public function test_outside_the_stable_panel_admins_keep_their_own_permissions(): void
    {
        $clerk = $this->makeAdminUser('clerk@example.com');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(Gate::forUser($clerk)->allows('update', $this->offering));
        $this->assertFalse(Gate::forUser($this->stableEditor())->allows('update', $this->offering));
    }
}
