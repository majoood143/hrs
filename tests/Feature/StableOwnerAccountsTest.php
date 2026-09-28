<?php

namespace Tests\Feature;

use App\Enums\FeeType;
use App\Enums\StableApprovalStatus;
use App\Filament\Resources\StableResource\Pages\ListStables;
use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Filament\Stable\Pages\Auth\Login;
use App\Filament\Stable\Pages\Auth\Register;
use App\Filament\Stable\Pages\BookingSettings;
use App\Filament\Stable\Resources\StableOfferings\Pages\CreateStableOffering;
use App\Filament\Stable\Resources\StableOfferings\Pages\ListStableOfferings;
use App\Filament\Stable\Resources\StableSchedules\Pages\CreateStableSchedule;
use App\Jobs\SendStableEmail;
use App\Jobs\SendWhatsAppMessage;
use App\Mail\StableRegisteredMail;
use App\Mail\StableReviewedMail;
use App\Models\BookingSlot;
use App\Models\City;
use App\Models\CustomerOtp;
use App\Models\NotificationLog;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Auth\CustomerOtpService;
use App\Services\Stables\StableApproval;
use App\Services\Stables\StableRegistration;
use App\Services\WhatsApp\BilingualMessage;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Stable owners: signing up, the admins' approval, what they may reach, and who is told. */
class StableOwnerAccountsTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['sms.driver' => 'demo']);
        $this->makeLocation();

        // the captcha field draws a new code on every render: with one letter it is always the same
        config(['filament-captcha.charset' => 'a']);
    }

    private function captcha(): string
    {
        return str_repeat('a', (int) config('filament-captcha.length', 5));
    }

    private function registration(): StableRegistration
    {
        return app(StableRegistration::class);
    }

    /** @return array<string, mixed> */
    private function stableData(array $overrides = []): array
    {
        $city = City::query()->first();

        return $overrides + [
            'en_name' => 'Al Seeb Riding Club',
            'ar_name' => 'نادي السيب للفروسية',
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
        ];
    }

    private function enterStablePanel(User $owner, Stable $stable): void
    {
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($stable);
        // a real request boots the panel in its middleware, which adds the tenant scopes
        Filament::bootCurrentPanel();
    }

    private function superAdmin(string $email = 'boss@example.com'): User
    {
        $admin = $this->makeAdminUser($email);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    // ── registration ─────────────────────────────────────────────────────────

    public function test_registering_creates_a_pending_stable_owned_by_a_verified_owner(): void
    {
        Queue::fake();

        $owner = $this->registration()->register(
            ['name' => 'Salim', 'email' => 'Salim@Example.com', 'phone' => '9123 4567', 'password' => 'secret-password'],
            $this->stableData(),
        );

        $this->assertSame(User::TYPE_STABLE_OWNER, $owner->type);
        $this->assertSame('salim@example.com', $owner->email);
        $this->assertSame('96891234567', $owner->phone);
        $this->assertNotNull($owner->phone_verified_at);

        $stable = $owner->stables()->sole();
        $this->assertSame(StableApprovalStatus::Pending, $stable->approval_status);
        $this->assertSame('al-seeb-riding-club', $stable->slug);
        $this->assertSame('owner', $stable->pivot->role);
        $this->assertSame('96891234567', $stable->phone);
        $this->assertFalse($stable->acceptsBookings());
        $this->assertFalse(Stable::query()->active()->whereKey($stable->id)->exists());

        // a second stable with the same English name gets its own slug
        $this->assertSame('al-seeb-riding-club-2', $this->registration()->createStable($owner, $this->stableData())->slug);
    }

    public function test_the_admins_are_emailed_and_whatsapped_about_a_new_stable(): void
    {
        Queue::fake();
        $this->superAdmin();
        $this->makeAdminUser('clerk@example.com'); // no role: not told
        $this->stableSiteSettings([
            'sms.driver' => 'demo',
            'whatsapp.enabled' => true,
            'whatsapp.recipients' => [['name' => 'Ops', 'phone' => '99887766']],
        ]);

        $this->registration()->register(
            ['name' => 'Salim', 'email' => 'salim@example.com', 'phone' => '91234567', 'password' => 'secret-password'],
            $this->stableData(),
        );

        Queue::assertPushed(SendStableEmail::class, 1);
        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->to === 'boss@example.com'
            && $job->mail instanceof StableRegisteredMail && $job->type === 'stable_registered');

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            [$en, $ar] = explode(BilingualMessage::DIVIDER, $job->message);

            return $job->to === '96899887766'
                && str_contains($en, 'Al Seeb Riding Club') && str_contains($en, 'waiting for approval')
                && str_contains($ar, 'نادي السيب للفروسية') && str_contains($ar, 'بانتظار الموافقة');
        });
    }

    public function test_the_whatsapp_alert_can_be_switched_off(): void
    {
        Queue::fake();
        $this->stableSiteSettings([
            'whatsapp.enabled' => true,
            'whatsapp.recipients' => [['name' => 'Ops', 'phone' => '99887766']],
            'whatsapp.alerts' => ['stable_registration' => false],
        ]);

        $this->registration()->register(
            ['name' => 'Salim', 'email' => 'salim@example.com', 'phone' => '91234567', 'password' => 'secret-password'],
            $this->stableData(),
        );

        Queue::assertNotPushed(SendWhatsAppMessage::class);
    }

    // ── approval ─────────────────────────────────────────────────────────────

    public function test_approving_sets_the_commission_opens_slots_and_tells_the_owner(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-10-04 05:30:00');
        $owner = $this->makeOwner();
        $stable = $this->makeStable($owner, status: StableApprovalStatus::Pending);
        $this->makeSchedule($this->makeOffering($stable), [2], ['10:00']);
        $this->assertSame(0, BookingSlot::query()->count());

        app(StableApproval::class)->approve($stable, FeeType::Percentage, '12.5', $admin = $this->makeAdminUser());

        $stable->refresh();
        $this->assertTrue($stable->acceptsBookings());
        $this->assertSame('12.5%', $stable->formatted_commission);
        $this->assertSame($admin->id, $stable->approved_by);
        $this->assertTrue(Stable::query()->active()->whereKey($stable->id)->exists());
        $this->assertGreaterThan(0, BookingSlot::query()->count());

        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->to === 'owner@example.com'
            && $job->mail instanceof StableReviewedMail && $job->type === 'stable_approved');
        // the demo SMS driver still logs the message
        $sms = NotificationLog::query()->where('channel', 'sms')->sole();
        $this->assertSame(['96891234567', 'stable_approved'], [$sms->recipient, $sms->type]);
        $this->assertStringContainsString('Desert Riders is approved', $sms->message);

        Carbon::setTestNow();
    }

    public function test_the_owner_is_told_in_their_own_language_and_a_rejection_carries_the_reason(): void
    {
        Queue::fake();
        $owner = $this->makeOwner();
        $owner->update(['locale' => 'ar']);
        $stable = $this->makeStable($owner, status: StableApprovalStatus::Pending);

        app(StableApproval::class)->reject($stable, 'Please add photos of the arena.');

        $this->assertSame(StableApprovalStatus::Rejected, $stable->refresh()->approval_status);
        $this->assertSame('Please add photos of the arena.', $stable->rejection_reason);
        $this->assertStringContainsString('لم تتم الموافقة', NotificationLog::query()->where('channel', 'sms')->sole()->message);

        Queue::assertPushed(SendStableEmail::class, function (SendStableEmail $job) {
            $html = $job->mail->render();

            return $job->mail->locale === 'ar' && str_contains($html, 'Please add photos of the arena.');
        });
    }

    public function test_a_commission_must_be_a_sensible_number(): void
    {
        $stable = $this->makeStable(status: StableApprovalStatus::Pending);

        $this->expectException(InvalidArgumentException::class);

        app(StableApproval::class)->approve($stable, FeeType::Percentage, 120);
    }

    public function test_existing_admin_stables_stay_public(): void
    {
        $stable = $this->makeStable(status: StableApprovalStatus::Approved);

        $this->assertTrue(Stable::query()->active()->whereKey($stable->id)->exists());
    }

    // ── who may go where ─────────────────────────────────────────────────────

    public function test_owners_use_the_stable_panel_only_and_only_their_own_stables(): void
    {
        $owner = $this->makeOwner();
        $mine = $this->makeStable($owner);
        $theirs = $this->makeStable($this->makeOwner('other@example.com', '96899999999'));
        $admin = $this->makeAdminUser();

        $this->assertTrue($owner->canAccessPanel(Filament::getPanel('stable')));
        $this->assertFalse($owner->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('stable')));

        $this->assertTrue($owner->canAccessTenant($mine));
        $this->assertFalse($owner->canAccessTenant($theirs));
        $this->assertSame([$mine->id], $owner->getTenants(Filament::getPanel('stable'))->pluck('id')->all());

        $myOffering = $this->makeOffering($mine);
        $theirOffering = $this->makeOffering($theirs);
        $this->assertTrue(Gate::forUser($owner)->allows('update', $myOffering));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $theirOffering));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $mine));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $theirs));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $mine));
    }

    public function test_the_owner_panel_lists_only_the_current_stables_records(): void
    {
        $owner = $this->makeOwner();
        $mine = $this->makeStable($owner);
        $theirs = $this->makeStable($this->makeOwner('other@example.com', '96899999999'));
        $this->makeOffering($mine, ['en_name' => 'My lesson']);
        $this->makeOffering($theirs, ['en_name' => 'Their lesson']);

        $this->enterStablePanel($owner, $mine);

        Livewire::test(ListStableOfferings::class)
            ->assertOk()
            ->assertSee('My lesson')
            ->assertDontSee('Their lesson');

        // a new record lands in the current stable
        Livewire::test(CreateStableOffering::class)
            ->fillForm([
                'type' => 'riding_training',
                'en_name' => 'Private lesson',
                'ar_name' => 'حصة خاصة',
                'price' => 25,
                'duration_minutes' => 45,
                'booking_cutoff_minutes' => 120,
                'capacity' => 1,
                'min_riders' => 1,
                'max_riders' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($mine->id, StableOffering::query()->where('en_name', 'Private lesson')->sole()->stable_id);
    }

    public function test_a_schedule_saved_in_the_panel_makes_its_slots(): void
    {
        Carbon::setTestNow('2026-10-04 05:30:00');
        $owner = $this->makeOwner();
        $stable = $this->makeStable($owner);
        $offering = $this->makeOffering($stable);

        $this->enterStablePanel($owner, $stable);

        Livewire::test(CreateStableSchedule::class)
            ->fillForm([
                'stable_offering_id' => $offering->id,
                'weekdays' => [2],
                'start_times' => [['time' => '16:00']],
                'valid_from' => '2026-10-04',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Tuesdays within the default 30 days: Oct 6, 13, 20, 27
        $this->assertSame(4, BookingSlot::query()->where('stable_id', $stable->id)->count());

        Carbon::setTestNow();
    }

    public function test_the_booking_settings_page_saves_the_owners_rules(): void
    {
        $owner = $this->makeOwner();
        $stable = $this->makeStable($owner);

        $this->enterStablePanel($owner, $stable);

        Livewire::test(BookingSettings::class)
            ->fillForm([
                'horizon_days' => 60,
                'allow_cancellation' => true,
                'cancellation_hours' => 12,
                'payment_options' => ['online', 'at_stable'],
                'alert_channels' => ['email', 'whatsapp'],
                'staff_enabled' => true,
                'rider_fields.weight.show' => true,
                'rider_fields.weight.required' => true,
                'rider_fields.notes.show' => false,
                'rider_fields.notes.required' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $stable->refresh()->bookingSettings();
        $this->assertSame(60, $settings->horizonDays());
        $this->assertSame(12, $settings->cancellationHours());
        $this->assertSame(['online', 'at_stable'], $settings->paymentOptions());
        $this->assertSame(['email', 'whatsapp'], $settings->alertChannels());
        $this->assertTrue($settings->staffEnabled());
        $this->assertTrue($settings->requiresRiderField('weight'));
        // hidden fields are never required
        $this->assertFalse($settings->requiresRiderField('notes'));
    }

    // ── sign-up and sign-in pages ────────────────────────────────────────────

    public function test_an_owner_signs_up_with_an_sms_code(): void
    {
        Queue::fake();
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        $code = app(CustomerOtpService::class)->request('91234567', '127.0.0.1')->demoCode;
        // make the code start with 0 (as one in ten do) to prove it is kept as typed
        CustomerOtp::query()->latest('id')->first()->forceFill(['code_hash' => hash_hmac('sha256', $code = '012345', config('app.key').'|96891234567')])->save();
        $form = fn (string $otp) => $this->stableData() + [
            'name' => 'Salim',
            'email' => 'salim@example.com',
            'phone' => '91234567',
            'otp_code' => $otp,
            'password' => 'secret-password',
            'passwordConfirmation' => 'secret-password',
        ];

        $page = Livewire::test(Register::class);
        $page->fillForm($form($code === '000000' ? '111111' : '000000') + ['captcha' => $this->captcha()])
            ->call('register')
            ->assertHasFormErrors(['otp_code']);
        $this->assertSame(0, User::query()->count());

        $page = Livewire::test(Register::class);
        $page->fillForm($form($code) + ['captcha' => $this->captcha()])
            ->call('register')
            ->assertHasNoFormErrors();

        $owner = User::query()->sole();
        $this->assertTrue($owner->isStableOwner());
        $this->assertSame(StableApprovalStatus::Pending, $owner->stables()->sole()->approval_status);
        $this->assertTrue(Auth::check());
    }

    public function test_an_owner_signs_in_with_an_sms_code(): void
    {
        $owner = $this->makeOwner();
        $this->makeStable($owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        $code = app(CustomerOtpService::class)->request('91234567', '127.0.0.1')->demoCode;
        $page = Livewire::test(Login::class);
        $page->fillForm(['method' => 'phone', 'phone' => '9123 4567', 'otp_code' => $code, 'captcha' => $this->captcha()])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertSame($owner->id, Auth::id());
    }

    public function test_a_phone_without_an_owner_account_cannot_sign_in(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        $code = app(CustomerOtpService::class)->request('91112222', '127.0.0.1')->demoCode;
        $page = Livewire::test(Login::class);
        $page->fillForm(['method' => 'phone', 'phone' => '91112222', 'otp_code' => $code, 'captcha' => $this->captcha()])
            ->call('authenticate')
            ->assertHasFormErrors(['phone']);

        $this->assertFalse(Auth::check());
    }

    // ── admin ────────────────────────────────────────────────────────────────

    public function test_an_admin_approves_a_stable_from_the_list(): void
    {
        Queue::fake();
        $stable = $this->makeStable($this->makeOwner(), status: StableApprovalStatus::Pending);

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListStables::class)
            ->assertOk()
            ->callTableAction('approveStable', $stable, ['commission_type' => 'fixed', 'commission_value' => '1.5'])
            ->assertHasNoTableActionErrors();

        $stable->refresh();
        $this->assertTrue($stable->isApproved());
        $this->assertSame([FeeType::Fixed, '1.500'], [$stable->commission_type, $stable->commission_value]);
    }

    public function test_the_admin_view_shows_the_owner_and_can_link_another(): void
    {
        $owner = $this->makeOwner();
        $stable = $this->makeStable($owner);
        $newcomer = $this->makeOwner('newcomer@example.com', '96890000000');

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewStable::class, ['record' => $stable->slug])
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('10%')
            ->callAction('linkStableOwner', ['user_id' => $newcomer->id])
            ->assertHasNoActionErrors();

        $this->assertTrue($newcomer->belongsToStable($stable->id));
    }
}
