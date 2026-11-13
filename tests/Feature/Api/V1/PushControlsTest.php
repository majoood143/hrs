<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\PushCampaignResource\Pages\CreatePushCampaign;
use App\Jobs\SendPushCampaign;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\User;
use App\Services\Push\ExpoPush;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * The campaign form's controls: chosen phones and stables, Expo's delivery fields, quiet hours,
 * past the daily limit, spread over minutes, the preview and the test from the form.
 */
class PushControlsTest extends TestCase
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

        Http::fake(['exp.host/*' => fn (Request $request) => Http::response(['data' => array_map(fn () => ['status' => 'ok'], $request->data())])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function token(string $type, int $id): int
    {
        return DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => $type, 'tokenable_id' => $id, 'name' => 'app', 'token' => bin2hex(random_bytes(16)), 'abilities' => '["*"]',
        ]);
    }

    private function campaign(array $attributes = []): PushCampaign
    {
        return PushCampaign::create($attributes + ['en_title' => 'Hello', 'ar_title' => 'مرحبا', 'scheduled_at' => now()->subMinute()]);
    }

    public function test_chosen_phones_and_stables_pick_their_signed_in_phones(): void
    {
        $customer = Customer::create(['phone' => '96892223333']);
        $mine = $this->device('mine', ['customer_id' => $customer->id, 'customer_token_id' => $this->token('customer', $customer->id)]);
        $this->device('other');
        $owner = User::create(['name' => 'Owner', 'email' => 'o@example.com', 'password' => 'secret-password', 'phone' => '96891112222']);
        $stable = $this->makeStable($owner);
        $ownerPhone = $this->device('owner', ['user_id' => $owner->id, 'owner_token_id' => $this->token('user', $owner->id)]);

        $this->assertSame([$mine->id], $this->campaign(['audience' => 'phones', 'target_phones' => ['96892223333']])->devices()->modelKeys());
        $this->assertSame([], $this->campaign(['audience' => 'phones', 'target_phones' => []])->devices()->modelKeys());
        $this->assertSame([$ownerPhone->id], $this->campaign(['audience' => 'stable_owners', 'target_stables' => [$stable->id]])->devices()->modelKeys());
        $this->assertSame([], $this->campaign(['audience' => 'stable_customers', 'target_stables' => [$stable->id]])->devices()->modelKeys());

        $offering = $this->makeOffering($stable);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, range(0, 6), ['10:00']));
        $booking = $this->book(BookingSlot::query()->firstOrFail(), 1);
        $booking->forceFill(['customer_id' => $customer->id])->save();
        $this->assertSame([$mine->id], $this->campaign(['audience' => 'stable_customers', 'target_stables' => [$stable->id]])->devices()->modelKeys());
    }

    public function test_delivery_options_go_to_expo(): void
    {
        $this->device('a');
        $campaign = $this->campaign(['priority' => 'high', 'ttl_hours' => 2, 'sound' => false, 'channel' => 'bookings']);

        (new SendPushCampaign($campaign->id))->handle(app(ExpoPush::class));

        Http::assertSent(fn (Request $request) => $request[0]['priority'] === 'high' && $request[0]['ttl'] === 7200
            && array_key_exists('sound', $request[0]) && $request[0]['sound'] === null && $request[0]['channelId'] === 'bookings');
        $this->assertSame(['sent', 1], [$campaign->refresh()->status, $campaign->accepted]);
    }

    public function test_quiet_hours_hold_it_until_the_morning(): void
    {
        $this->device('a');
        Carbon::setTestNow(Carbon::parse('2026-11-12 23:30', 'Asia/Muscat'));
        $campaign = $this->campaign(['quiet_hours' => true]);

        Artisan::call('push:send-due');

        $this->assertSame('scheduled', $campaign->refresh()->status);
        $this->assertTrue($campaign->scheduled_at->equalTo(Carbon::parse('2026-11-13 07:00', 'Asia/Muscat')));
        Http::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-11-13 07:01', 'Asia/Muscat'));
        Artisan::call('push:send-due');
        $this->assertSame('sent', $campaign->refresh()->status);
    }

    public function test_urgent_goes_past_the_daily_limit(): void
    {
        $device = $this->device('a');
        foreach (range(1, PushCampaign::DAILY_CAP) as $i) {
            $this->campaign()->deliveries()->create(['mobile_device_id' => $device->id, 'created_at' => now()]);
        }

        $this->assertCount(0, $this->campaign()->devices());
        $this->assertCount(1, $this->campaign(['ignore_cap' => true])->devices());
    }

    public function test_a_spread_campaign_goes_out_in_batches_one_a_minute(): void
    {
        foreach (range(1, 5) as $i) {
            $this->device("p{$i}");
        }
        $campaign = $this->campaign(['spread_minutes' => 3]);

        Artisan::call('push:send-due');
        $this->assertSame(['sending', 5, 2, 3], [$campaign->refresh()->status, $campaign->recipients, $campaign->accepted, count($campaign->pending_devices)]);

        Artisan::call('push:send-due'); // the next batch is not due yet
        $this->assertSame(2, $campaign->refresh()->accepted);

        Carbon::setTestNow(now()->addMinutes(1)->addSecond());
        Artisan::call('push:send-due');
        $this->assertSame(4, $campaign->refresh()->accepted);

        Carbon::setTestNow(now()->addMinutes(1)->addSecond());
        Artisan::call('push:send-due');
        $this->assertSame(['sent', 5, null], [$campaign->refresh()->status, $campaign->accepted, $campaign->pending_devices]);
        $this->assertSame(5, $campaign->deliveries()->count());
    }

    public function test_the_form_previews_and_tests_the_unsaved_message_and_saves_the_controls(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $customer = Customer::create(['phone' => '96892223333']);
        $this->device('mine', ['customer_id' => $customer->id, 'customer_token_id' => $this->token('customer', $customer->id)]);

        Livewire::test(CreatePushCampaign::class)
            ->fillForm(['en_title' => 'Gallop sale', 'ar_title' => 'تخفيضات'])
            ->assertSee('Gallop sale')
            ->assertSee('تخفيضات')
            ->callAction('send_test', ['test_phone' => '9222 3333'])
            ->assertNotified(__('admin_mobile_app.campaigns.test_sent', ['count' => 1]))
            ->fillForm(['audience' => 'phones', 'target_phones' => "9222 3333\n٩٢٢٢٣٣٣٣, 99887766", 'priority' => 'high', 'spread_minutes' => 10, 'quiet_hours' => true, 'ignore_cap' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = PushCampaign::query()->sole();
        $this->assertSame([['96892223333', '96899887766'], 'high', 10, true, true, 'draft'],
            [$campaign->target_phones, $campaign->priority, $campaign->spread_minutes, $campaign->quiet_hours, $campaign->ignore_cap, $campaign->status]);
        Http::assertSentCount(1);
    }
}
