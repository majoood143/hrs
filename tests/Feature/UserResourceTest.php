<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings();
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
    }

    public function test_a_stable_owner_can_be_edited_without_retyping_the_password(): void
    {
        $this->actingAs($this->makeAdminUser());
        $owner = $this->makeOwner();

        // the owner registered without civil ID, CR number, postal code or address
        Livewire::test(EditUser::class, ['record' => $owner->getRouteKey()])
            ->fillForm(['name' => 'Salim Renamed', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $owner->refresh();
        $this->assertSame('Salim Renamed', $owner->name);
        $this->assertTrue(Hash::check('secret-password', $owner->password));
    }

    public function test_creating_a_user_hashes_the_password_normalizes_the_phone_and_attaches_roles(): void
    {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'editor@example.com',
                'phone' => '9123 4567',
                'type' => 'owner',
                'roles' => [Role::findByName('editor')->id],
                'password' => 'Str0ng-password!',
                'password_confirmation' => 'Str0ng-password!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->assertSame('96891234567', $user->phone);
        $this->assertTrue(Hash::check('Str0ng-password!', $user->password));
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_the_password_confirmation_must_match(): void
    {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Someone',
                'email' => 'someone@example.com',
                'type' => 'owner',
                'password' => 'Str0ng-password!',
                'password_confirmation' => 'different',
            ])
            ->call('create')
            ->assertHasFormErrors(['password_confirmation']);
    }

    public function test_only_a_super_admin_can_touch_a_super_admins_roles(): void
    {
        $super = $this->makeAdminUser('super@example.com');
        $super->assignRole('super_admin');

        $this->actingAs($this->makeAdminUser());
        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->assertFormFieldIsDisabled('roles')
            ->fillForm(['name' => 'Still Super'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($super->fresh()->hasRole('super_admin'));

        $this->actingAs($super);
        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->assertFormFieldIsEnabled('roles')
            ->assertFormFieldIsDisabled('type');
    }

    public function test_the_list_shows_tabs_by_type_and_role(): void
    {
        $admin = $this->makeAdminUser();
        $admin->assignRole('editor');
        $owner = $this->makeOwner();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $owner])
            ->set('activeTab', 'staff')
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$owner])
            ->set('activeTab', User::TYPE_STABLE_OWNER)
            ->assertCanSeeTableRecords([$owner])
            ->assertCanNotSeeTableRecords([$admin]);
    }
}
