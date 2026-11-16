<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppEvent;
use App\Models\CrmSegment;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Support\MobileApp\MobileOnboarding;
use App\Support\MobileApp\MobileProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * The CRM's data from the app: events by install (anonymous, then the customer's from sign-in),
 * the optional profile, what account deletion removes, and the first-launch slides.
 */
class CrmTest extends TestCase
{
    use PreparesStableBookings;

    private const INSTALL = ['X-Install-Id' => 'a1b2c3d4-0000-4000-8000-123456789abc', 'X-App-Platform' => 'ios', 'X-App-Version' => '1.2.0'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['sms.driver' => 'demo']);
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
    }

    private function signIn(string $phone = '91234567', array $extra = []): string
    {
        $code = $this->postJson('/api/v1/auth/request', ['phone' => $phone, 'channel' => 'sms'])->assertOk()->json('demo_code');

        return $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code] + $extra, self::INSTALL)->assertOk()->json('token');
    }

    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_events_need_an_install_id_and_only_known_app_events_are_kept(): void
    {
        $this->postJson('/api/v1/events', ['events' => [['name' => 'app_launched']]])->assertUnprocessable()->assertJsonValidationErrors('install_id');

        $this->postJson('/api/v1/events', ['events' => [
            ['name' => 'listing_viewed', 'screen' => '/horses/12', 'properties' => ['section' => 'horses', 'id' => 12, 'list' => [1, 2], 'note' => str_repeat('x', 300)]],
            ['name' => 'booking_confirmed'], // the server's alone
            ['name' => 'made_up'],
            ['name' => 'app_launched', 'at' => now()->subYear()->toIso8601String()],
        ]], self::INSTALL)->assertStatus(202)->assertJsonPath('accepted', 2);

        $viewed = AppEvent::query()->where('name', 'listing_viewed')->sole();
        $this->assertSame(['section' => 'horses', 'id' => 12, 'note' => str_repeat('x', 255)], $viewed->properties);
        $this->assertSame(['/horses/12', 'ios', '1.2.0', null], [$viewed->screen, $viewed->platform, $viewed->app_version, $viewed->customer_id]);
        // a phone clock a year off is not believed
        $this->assertTrue(AppEvent::query()->where('name', 'app_launched')->sole()->occurred_at->isToday());

        $this->postJson('/api/v1/events', ['events' => array_fill(0, 51, ['name' => 'app_launched'])], self::INSTALL)->assertUnprocessable();
    }

    public function test_signing_up_claims_the_installs_earlier_events_and_later_ones_are_the_customers(): void
    {
        $this->postJson('/api/v1/events', ['events' => [['name' => 'onboarding_completed']]], self::INSTALL)->assertStatus(202);
        $this->postJson('/api/v1/events', ['events' => [['name' => 'app_launched']]], ['X-Install-Id' => 'another-install-1'])->assertStatus(202);

        $token = $this->signIn(extra: ['country' => 'om']);
        $customer = Customer::query()->sole();

        $this->assertSame(['app', 'OM'], [$customer->source, $customer->country]);
        $this->assertSame(['onboarding_completed', 'signed_up'], $customer->events()->orderBy('id')->pluck('name')->all());
        $this->assertNull(AppEvent::query()->where('install_id', 'another-install-1')->value('customer_id'));

        $this->as($token)->postJson('/api/v1/events', ['events' => [['name' => 'contact_tapped']]], self::INSTALL)->assertStatus(202);
        $this->assertSame($customer->id, AppEvent::query()->where('name', 'contact_tapped')->value('customer_id'));

        // signing in again is not a sign-up
        $this->travel(5)->minutes();
        $this->signIn();
        $this->assertSame(1, AppEvent::query()->where('name', 'signed_up')->count());
    }

    public function test_the_optional_profile_is_saved_with_the_id_number_encrypted_and_masked(): void
    {
        $token = $this->signIn();

        $this->as($token)->postJson('/api/v1/account', ['gender' => 'other', 'date_of_birth' => now()->addDay()->toDateString(), 'national_id' => '12-34'])
            ->assertUnprocessable()->assertJsonValidationErrors(['gender', 'date_of_birth', 'national_id']);

        $this->as($token)->getJson('/api/v1/account')->assertJsonPath('data.profile_completion', 0);

        $this->as($token)->postJson('/api/v1/account', ['name' => 'Aisha', 'gender' => 'female', 'date_of_birth' => '1995-04-20', 'national_id' => '12345678', 'marketing_opt_in' => true])
            ->assertOk()
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.date_of_birth', '1995-04-20')
            ->assertJsonPath('data.national_id', '••••5678')
            ->assertJsonPath('data.marketing_opt_in', true)
            ->assertJsonPath('data.profile_completion', 50);

        $this->assertNotSame('12345678', DB::table('customers')->value('national_id'));
        $this->assertSame('12345678', Customer::query()->sole()->national_id);

        // a field left out stays; the event names the fields, never their values
        $this->as($token)->postJson('/api/v1/account', ['email' => 'aisha@example.com'])->assertOk()->assertJsonPath('data.gender', 'female')->assertJsonPath('data.profile_completion', 63);
        $updates = AppEvent::query()->where('name', 'profile_updated')->orderBy('id')->get();
        $this->assertSame('name,gender,date_of_birth,national_id,marketing_opt_in', $updates[0]->properties['fields']);
        $this->assertStringNotContainsString('1234', json_encode($updates->pluck('properties')));
        $this->assertSame(['fields' => 'email', 'completion' => 63], $updates[1]->properties);
    }

    public function test_country_and_region_are_public_ones_and_the_photo_is_stored(): void
    {
        Storage::fake('public');
        $oman = DB::table('countries')->insertGetId(['en_name' => 'Oman', 'ar_name' => 'عمان', 'is_public' => true]);
        $hidden = DB::table('countries')->insertGetId(['en_name' => 'Hidden', 'ar_name' => 'مخفية', 'is_public' => false]);
        $uae = DB::table('countries')->insertGetId(['en_name' => 'UAE', 'ar_name' => 'الإمارات', 'is_public' => true]);
        $muscat = DB::table('regions')->insertGetId(['country_id' => $oman, 'en_name' => 'Muscat', 'ar_name' => 'مسقط']);
        $dubai = DB::table('regions')->insertGetId(['country_id' => $uae, 'en_name' => 'Dubai', 'ar_name' => 'دبي']);
        $token = $this->signIn();

        // a hidden country, a region of another country
        $this->as($token)->postJson('/api/v1/account', ['country_id' => $hidden])->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->as($token)->postJson('/api/v1/account', ['country_id' => $oman, 'region_id' => $dubai])->assertUnprocessable()->assertJsonValidationErrors('region_id');

        $this->as($token)->postJson('/api/v1/account', ['country_id' => $oman, 'region_id' => $muscat])->assertOk()
            ->assertJsonPath('data.country_id', $oman)->assertJsonPath('data.region_id', $muscat);
        // the region alone checks against the saved country; a new country drops the old region
        $this->as($token)->postJson('/api/v1/account', ['region_id' => $dubai])->assertUnprocessable();
        $this->as($token)->postJson('/api/v1/account', ['country_id' => $uae])->assertOk()->assertJsonPath('data.region_id', null);
        $this->as($token)->postJson('/api/v1/account', ['region_id' => $dubai])->assertOk()->assertJsonPath('data.region_id', $dubai);

        $this->as($token)->post('/api/v1/account/photo', ['photo' => 'not a file'], ['Accept' => 'application/json'])->assertUnprocessable();
        $first = $this->as($token)->post('/api/v1/account/photo', ['photo' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])->assertOk()->json('data.photo_url');
        $path = Customer::query()->sole()->profile_photo;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, $first);

        // a new photo replaces the old file; removing it deletes the file
        $this->as($token)->post('/api/v1/account/photo', ['photo' => UploadedFile::fake()->image('again.jpg')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->as($token)->deleteJson('/api/v1/account/photo')->assertOk()->assertJsonPath('data.photo_url', null);
        $this->assertSame([], Storage::disk('public')->allFiles('customers/photos'));

        // segments by where they live
        $this->assertSame(1, CrmSegment::queryFor(['profile_countries' => [$uae]])->count());
        $this->assertSame(1, CrmSegment::queryFor(['regions' => [$dubai]])->count());
        $this->assertSame(0, CrmSegment::queryFor(['regions' => [$muscat]])->count());
    }

    public function test_the_admin_chooses_the_profile_fields_and_completion_counts_only_those(): void
    {
        $token = $this->signIn();
        Cache::forever('site_settings.all', collect(['mobile.profile' => (object) ['key' => 'mobile.profile', 'type' => 'text', 'value' => json_encode(['fields' => ['email', 'region']])]]));
        SiteSetting::resetMemo();

        // a region without its country is not shown
        $this->assertSame(['email'], MobileProfile::shown());
        $this->getJson('/api/v1/app-status')->assertOk()->assertJsonPath('data.profile_fields', ['email']);

        $this->as($token)->postJson('/api/v1/account', ['name' => 'Aisha'])->assertOk()->assertJsonPath('data.profile_completion', 50);
        $this->assertSame(1, CrmSegment::queryFor(['min_completion' => 50])->count());
    }

    public function test_deleting_the_account_deletes_its_events(): void
    {
        $token = $this->signIn();
        $this->assertGreaterThan(0, AppEvent::query()->count());

        $this->as($token)->deleteJson('/api/v1/account')->assertOk();

        $this->assertSame(0, AppEvent::query()->count());
    }

    public function test_events_older_than_a_year_are_pruned(): void
    {
        AppEvent::query()->insert([
            ['install_id' => 'old-install-1', 'name' => 'app_launched', 'occurred_at' => now()->subMonths(13)],
            ['install_id' => 'old-install-1', 'name' => 'app_launched', 'occurred_at' => now()->subMonths(11)],
        ]);

        $this->artisan('model:prune', ['--model' => [AppEvent::class]])->assertSuccessful();

        $this->assertSame(1, AppEvent::query()->count());
    }

    public function test_app_status_sends_the_onboarding_slides_in_the_language_asked(): void
    {
        Storage::fake('public');
        Cache::forget('site_settings.all');
        SiteSetting::resetMemo();
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });
        $this->getJson('/api/v1/app-status')->assertOk()->assertJsonPath('data.onboarding', ['enabled' => false, 'version' => null, 'slides' => []]);

        MobileOnboarding::save(['enabled' => true, 'slides' => [
            ['image' => ['mobile-app/onboarding/one.jpg'], 'title' => ['en' => 'Horses', 'ar' => 'خيول'], 'text' => ['en' => 'Browse', 'ar' => '']],
            ['image' => null, 'title' => ['en' => '', 'ar' => ''], 'text' => []], // no title: left out
        ]]);

        $en = $this->getJson('/api/v1/app-status', ['Accept-Language' => 'en'])->json('data.onboarding');
        $ar = $this->getJson('/api/v1/app-status', ['Accept-Language' => 'ar'])->json('data.onboarding');

        $this->assertTrue($en['enabled']);
        $this->assertSame($en['version'], $ar['version']);
        $this->assertSame(['Horses', 'Browse'], [$en['slides'][0]['title'], $en['slides'][0]['text']]);
        $this->assertSame(['خيول', 'Browse'], [$ar['slides'][0]['title'], $ar['slides'][0]['text']]);
        $this->assertStringEndsWith('mobile-app/onboarding/one.jpg', $en['slides'][0]['image_url']);
        $this->assertCount(1, $en['slides']);

        // changing a slide changes the version (the app shows them again once); a replaced picture is deleted
        Storage::disk('public')->put('mobile-app/onboarding/one.jpg', 'x');
        MobileOnboarding::save(['enabled' => true, 'slides' => [['image' => 'mobile-app/onboarding/two.jpg', 'title' => ['en' => 'Horses'], 'text' => []]]]);
        $this->assertNotSame($en['version'], $this->getJson('/api/v1/app-status')->json('data.onboarding.version'));
        Storage::disk('public')->assertMissing('mobile-app/onboarding/one.jpg');
    }
}
