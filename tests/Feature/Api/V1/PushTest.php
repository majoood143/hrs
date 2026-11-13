<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\PushCampaignResource\Pages\CreatePushCampaign;
use App\Filament\Resources\PushCampaignResource\Pages\ListPushCampaigns;
use App\Models\Customer;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Services\Push\ExpoPush;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Push notifications for every phone: registering (topics, the signed-in customer), campaigns to an
 * audience (topic, platform, language, version, recent use, the daily cap), sent once, the app's
 * list and the opens.
 */
class PushTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['sms.driver' => 'demo']);

        foreach ([
            '2026_09_11_145142_create_personal_access_tokens_table',
            '2026_11_04_000002_create_push_campaigns_tables',
            '2026_11_07_000002_add_source_to_push_campaigns',
            '2026_11_12_000001_add_controls_to_push_campaigns',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    private function device(string $token, array $attributes = []): MobileDevice
    {
        return MobileDevice::create($attributes + [
            'expo_token' => "ExponentPushToken[{$token}]",
            'platform' => 'ios',
            'app_version' => '1.1.0',
            'locale' => 'en',
            'last_seen_at' => now(),
        ]);
    }

    private function signInToken(): int
    {
        return DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'customer', 'tokenable_id' => 1, 'name' => 'app', 'token' => bin2hex(random_bytes(16)), 'abilities' => '["*"]',
        ]);
    }

    public function test_a_phone_registers_with_its_topics_and_the_customer_signed_in_on_it(): void
    {
        $this->postJson('/api/v1/devices', ['token' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]', 'topics' => ['spam']])->assertUnprocessable();

        $this->withHeaders(['Accept-Language' => 'ar', 'X-App-Platform' => 'android', 'X-App-Version' => '1.1.0'])
            ->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]', 'topics' => ['offers', 'offers', 'racing']])->assertOk();
        $device = MobileDevice::query()->sole();
        $this->assertSame(['android', 'ar', ['offers', 'racing'], null], [$device->platform, $device->locale, $device->topics, $device->customer_id]);

        $code = $this->postJson('/api/v1/auth/request', ['phone' => '92223333', 'channel' => 'sms'])->json('demo_code');
        $token = $this->postJson('/api/v1/auth/verify', ['phone' => '92223333', 'code' => $code])->json('token');
        $this->withToken($token)->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]'])->assertOk();
        $this->assertNotNull($device->refresh()->customer_token_id);
        $this->assertSame(['offers', 'racing'], $device->topics);

        // signed out (no token sent): no longer the customer's phone
        $this->app['auth']->forgetGuards();
        $this->withToken('')->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]'])->assertOk();
        $this->assertNull($device->refresh()->customer_id);
    }

    public function test_a_campaign_goes_once_to_its_audience_and_the_app_lists_it_and_counts_opens(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok'], ['status' => 'ok']]])]);

        $guest = $this->device('guest');
        $customerId = Customer::create(['phone' => '96892223333'])->id;
        $customer = $this->device('customer', ['locale' => 'ar', 'customer_id' => $customerId, 'customer_token_id' => $this->signInToken()]);
        $this->device('racing-only', ['topics' => ['racing']]);
        $this->device('android', ['platform' => 'android']);
        $this->device('old-app', ['app_version' => '1.0.0']);
        $this->device('away', ['last_seen_at' => now()->subDays(40)]);
        $busy = $this->device('busy');
        foreach (range(1, PushCampaign::DAILY_CAP) as $i) {
            $earlier = PushCampaign::create(['en_title' => "Earlier {$i}", 'ar_title' => 'سابق', 'status' => 'sent']);
            PushDelivery::create(['push_campaign_id' => $earlier->id, 'mobile_device_id' => $busy->id]);
        }

        $campaign = PushCampaign::create([
            'en_title' => 'Spring offers', 'ar_title' => 'عروض الربيع', 'en_body' => 'Ten percent off.', 'ar_body' => 'خصم عشرة بالمئة.',
            'action_type' => 'screen', 'action_screen' => 'bookings',
            'topic' => 'offers', 'platforms' => ['ios'], 'min_version' => '1.1.0', 'active_days' => 30,
            'scheduled_at' => now()->subMinute(),
        ]);
        $this->assertSame('scheduled', $campaign->status);
        $this->assertEqualsCanonicalizing([$guest->id, $customer->id], $campaign->devices()->modelKeys());
        $this->assertSame([$customer->id], $campaign->fill(['audience' => 'customers'])->devices()->modelKeys());
        $this->assertSame([$guest->id], $campaign->fill(['audience' => 'guests'])->devices()->modelKeys());
        $campaign->update(['audience' => 'everyone']);

        $this->artisan('push:send-due')->assertSuccessful();
        $this->artisan('push:send-due')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => collect($request->data())->pluck('title', 'to')->all() === [
            'ExponentPushToken[guest]' => 'Spring offers',
            'ExponentPushToken[customer]' => 'عروض الربيع',
        ] && $request[0]['data'] === ['campaign' => $campaign->id, 'screen' => 'bookings']);
        $campaign->refresh();
        $this->assertSame(['sent', 2, 2], [$campaign->status, $campaign->recipients, $campaign->accepted]);

        $this->withHeaders(['Accept-Language' => 'ar', 'X-Push-Token' => 'ExponentPushToken[customer]'])->getJson('/api/v1/push/inbox')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campaign->id)
            ->assertJsonPath('data.0.title', 'عروض الربيع')
            ->assertJsonPath('data.0.screen', 'bookings')
            ->assertJsonPath('data.0.opened', false);
        $this->flushHeaders()->getJson('/api/v1/push/inbox')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/push/{$campaign->id}/opened", ['token' => 'ExponentPushToken[customer]'])->assertOk();
        $this->postJson("/api/v1/push/{$campaign->id}/opened", ['token' => 'ExponentPushToken[customer]'])->assertOk();
        $this->postJson("/api/v1/push/{$campaign->id}/opened", ['token' => 'ExponentPushToken[busy]'])->assertOk();
        $this->assertSame(1, $campaign->refresh()->opened);
    }

    public function test_a_failed_batch_does_not_stop_the_next_ones(): void
    {
        $devices = collect(range(1, 101))->map(fn (int $i) => $this->device("p{$i}"));
        Http::fakeSequence('exp.host/*')
            ->push('down', 502)
            ->push(['data' => [['status' => 'ok']]]);

        $accepted = app(ExpoPush::class)->send($devices, fn () => ['title' => 'Hi', 'body' => 'There']);

        Http::assertSentCount(2);
        $this->assertSame([$devices->last()->id], $accepted);
    }

    public function test_a_draft_is_not_sent_and_a_sent_campaign_cannot_change(): void
    {
        Http::fake();
        $draft = PushCampaign::create(['en_title' => 'Draft', 'ar_title' => 'مسودة']);
        $this->assertSame('draft', $draft->status);

        $this->artisan('push:send-due')->assertSuccessful();
        Http::assertNothingSent();

        $draft->forceFill(['status' => 'sent'])->save();
        $this->assertSame('sent', $draft->update(['scheduled_at' => now()]) ? $draft->refresh()->status : null);
        $this->assertFalse($draft->editable());
    }

    public function test_an_admin_writes_a_campaign_tests_it_and_sends_it_now(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $customerId = Customer::create(['phone' => '96892223333'])->id;
        $this->device('mine', ['customer_id' => $customerId, 'customer_token_id' => $this->signInToken()]);

        Livewire::test(CreatePushCampaign::class)
            ->assertSuccessful()
            ->fillForm(['en_title' => 'Race day', 'ar_title' => 'يوم السباق', 'action_type' => 'url', 'action_url' => 'https://example.com/racing', 'topic' => 'racing'])
            ->call('create')
            ->assertHasNoFormErrors();
        $campaign = PushCampaign::query()->sole();
        $this->assertSame(['draft', 'everyone', ['ios', 'android']], [$campaign->status, $campaign->audience, $campaign->platforms]);

        Livewire::test(ListPushCampaigns::class)
            ->callTableAction('send_test', $campaign, ['test_phone' => '9222 3333'])
            ->assertNotified(__('admin_mobile_app.campaigns.test_sent', ['count' => 1]));
        $this->assertSame(0, PushDelivery::query()->count());

        Livewire::test(ListPushCampaigns::class)->callTableAction('send_now', $campaign);
        $this->assertSame(['sent', 1], [$campaign->refresh()->status, $campaign->accepted]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request[0]['data'] === ['campaign' => $campaign->id, 'url' => 'https://example.com/racing']);
    }
}
