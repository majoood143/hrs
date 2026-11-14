<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmSegmentResource\Pages\CreateCrmSegment;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\EventsRelationManager;
use App\Models\AppEvent;
use App\Models\CrmSegment;
use App\Models\Customer;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** Admin → CRM: profiles (list, filters, page, timeline, the ID number), segments, and segments as a campaign audience. */
class CrmAdminTest extends TestCase
{
    use PreparesCustomerSite;

    private bool $mayReveal = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        Schema::create('users', fn (Blueprint $table) => $table->id());

        foreach ([
            '2026_09_11_145142_create_personal_access_tokens_table',
            '2026_11_04_000001_create_mobile_devices_table',
            '2026_11_07_000001_add_enabled_to_mobile_devices',
            '2026_11_04_000002_create_push_campaigns_tables',
            '2026_11_07_000002_add_source_to_push_campaigns',
            '2026_11_12_000001_add_controls_to_push_campaigns',
            '2026_11_14_000004_add_crm_segment_to_push_campaigns',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        // every ability, except the ID number when a test takes it away
        Gate::before(fn ($user, string $ability) => $ability === ViewCustomer::REVEAL_PERMISSION ? $this->mayReveal : true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function customer(string $phone, array $attributes = []): Customer
    {
        return Customer::query()->create($attributes + ['phone' => $phone]);
    }

    private function events(Customer $customer, string $name, int $times, int $daysAgo = 1): void
    {
        foreach (range(1, $times) as $_) {
            AppEvent::query()->create(['customer_id' => $customer->id, 'name' => $name, 'occurred_at' => now()->subDays($daysAgo)]);
        }
    }

    private function signedInPhone(Customer $customer, string $token): MobileDevice
    {
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => Customer::class, 'tokenable_id' => $customer->id, 'name' => 'app',
            'token' => bin2hex(random_bytes(16)), 'abilities' => '["customer"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return MobileDevice::query()->create([
            'expo_token' => "ExponentPushToken[{$token}]", 'platform' => 'ios', 'app_version' => '1.2.0', 'locale' => 'en',
            'customer_id' => $customer->id, 'customer_token_id' => $tokenId, 'last_seen_at' => now(),
        ]);
    }

    public function test_segments_combine_what_customers_did_with_who_they_are(): void
    {
        $keen = $this->customer('96891111111', ['gender' => 'female', 'date_of_birth' => now()->subYears(25), 'country' => 'OM', 'marketing_opt_in' => true, 'source' => 'app']);
        $once = $this->customer('96892222222', ['gender' => 'female', 'date_of_birth' => now()->subYears(25), 'country' => 'OM', 'marketing_opt_in' => true]);
        $old = $this->customer('96893333333', ['gender' => 'female', 'date_of_birth' => now()->subYears(25), 'country' => 'AE', 'marketing_opt_in' => false]);
        $caller = $this->customer('96894444444', ['gender' => 'male', 'date_of_birth' => now()->subYears(50)]);

        $this->events($keen, 'listing_viewed', 3);
        $this->events($once, 'listing_viewed', 1);
        $this->events($old, 'listing_viewed', 3, daysAgo: 40);
        $this->events($caller, 'listing_viewed', 5);
        $this->events($caller, 'contact_tapped', 1);

        $ids = fn (array $rules) => CrmSegment::queryFor($rules)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$keen->id, $caller->id], $ids(['did' => 'listing_viewed', 'min_count' => 3, 'within_days' => 30]));
        $this->assertSame([$keen->id], $ids(['did' => 'listing_viewed', 'min_count' => 3, 'within_days' => 30, 'did_not' => 'contact_tapped']));
        $this->assertSame([$keen->id, $once->id, $old->id], $ids(['gender' => 'female', 'age_min' => 18, 'age_max' => 30]));
        $this->assertSame([$caller->id], $ids(['age_min' => 50, 'age_max' => 50]));
        $this->assertSame([$keen->id, $once->id], $ids(['marketing_only' => true, 'countries' => ['OM']]));
        $this->assertSame([$keen->id], $ids(['source' => 'app']));

        $keen->forceFill(['name' => 'Aisha', 'email' => 'a@example.com', 'national_id' => '12345678'])->save();
        $this->assertSame(100, $keen->profileCompletion());
        $this->assertSame([$keen->id], $ids(['min_completion' => 100]));
        $this->assertSame([$keen->id, $once->id, $old->id, $caller->id], $ids(['min_completion' => 40]));
    }

    public function test_a_campaign_to_a_segment_reaches_only_its_customers_signed_in_phones(): void
    {
        $in = $this->customer('96891111111', ['gender' => 'female']);
        $out = $this->customer('96892222222', ['gender' => 'male']);
        $this->signedInPhone($in, 'in');
        $this->signedInPhone($out, 'out');
        MobileDevice::query()->create(['expo_token' => 'ExponentPushToken[guest]', 'platform' => 'ios', 'locale' => 'en', 'last_seen_at' => now()]);

        $segment = CrmSegment::query()->create(['name' => 'Women', 'rules' => ['gender' => 'female']]);
        $campaign = new PushCampaign(['audience' => 'segment', 'crm_segment_id' => $segment->id, 'topic' => 'news']);

        $this->assertSame(['ExponentPushToken[in]'], $campaign->devices()->pluck('expo_token')->all());

        $segment->delete();
        $this->assertCount(0, (new PushCampaign(['audience' => 'segment', 'crm_segment_id' => $segment->id, 'topic' => 'news']))->devices());
    }

    public function test_a_segment_is_built_in_the_admin_with_its_rules(): void
    {
        $this->customer('96891111111', ['gender' => 'female']);

        Livewire::test(CreateCrmSegment::class)
            ->fillForm(['name' => 'Women', 'rules.gender' => 'female', 'rules.marketing_only' => false])
            ->assertSee('1')
            ->call('create')
            ->assertHasNoFormErrors();

        $segment = CrmSegment::query()->sole();
        $this->assertSame('female', $segment->rules['gender']);
        $this->assertSame(1, $segment->customers()->count());
    }

    public function test_profiles_list_filter_and_show_the_timeline_with_the_id_number_masked(): void
    {
        $aisha = $this->customer('96891111111', ['name' => 'Aisha', 'source' => 'app', 'gender' => 'female', 'national_id' => '12345678']);
        $other = $this->customer('96892222222', ['name' => 'Other']);
        $this->events($aisha, 'contact_tapped', 2);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$aisha, $other])
            ->filterTable('source', 'app')
            ->assertCanSeeTableRecords([$aisha])
            ->assertCanNotSeeTableRecords([$other])
            ->resetTableFilters()
            ->filterTable('did', ['event' => 'contact_tapped', 'days' => 30])
            ->assertCanSeeTableRecords([$aisha])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ViewCustomer::class, ['record' => $aisha->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('••••5678')
            ->assertDontSee('12345678');

        Livewire::test(EventsRelationManager::class, ['ownerRecord' => $aisha, 'pageClass' => ViewCustomer::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($aisha->events);
    }

    public function test_the_full_id_number_needs_its_permission_and_is_logged(): void
    {
        $aisha = $this->customer('96891111111', ['national_id' => '12345678']);

        $this->mayReveal = false;
        Livewire::test(ViewCustomer::class, ['record' => $aisha->getRouteKey()])->assertActionHidden('revealNationalId');

        $this->mayReveal = true;
        Livewire::test(ViewCustomer::class, ['record' => $aisha->getRouteKey()])
            ->callAction('revealNationalId')
            ->assertNotified();

        $log = DB::table('activity_log')->where('log_name', 'crm')->sole();
        $this->assertSame('revealed', $log->event);
        $this->assertSame($aisha->id, (int) $log->subject_id);
    }
}
