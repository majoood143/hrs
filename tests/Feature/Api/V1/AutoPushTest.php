<?php

namespace Tests\Feature\Api\V1;

use App\Console\Commands\SendDuePushCampaigns;
use App\Jobs\SendPushCampaign;
use App\Models\CmsPost;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Models\SiteSetting;
use App\Models\ToolSalePost;
use App\Services\Push\ExpoPush;
use App\Support\MobileApp\MobileAutoPush;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileScreens;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** New content going live becomes a campaign when its section is on in the admin; the app's main switch stops everything to a phone. */
class AutoPushTest extends TestCase
{
    use PreparesMobileApi;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi(['currency_code' => 'OMR', 'currency_symbol' => 'OMR']);
        $this->ids = $this->seedLocations();

        Schema::create('users', fn (Blueprint $table) => $table->id());

        foreach (['2026_09_11_145142_create_personal_access_tokens_table', '2026_09_23_000001_create_customers_tables', '2026_11_14_000001_add_profile_to_customers', '2026_11_14_000002_create_app_events_table', '2026_11_04_000001_create_mobile_devices_table', '2026_11_07_000001_add_enabled_to_mobile_devices', '2026_11_04_000002_create_push_campaigns_tables', '2026_11_07_000002_add_source_to_push_campaigns', '2026_11_12_000001_add_controls_to_push_campaigns', '2026_11_04_000003_create_push_saved_searches_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    private function device(string $name, array $attributes = []): MobileDevice
    {
        return MobileDevice::create($attributes + ['expo_token' => "ExponentPushToken[{$name}]", 'platform' => 'ios', 'app_version' => '1.1.0', 'locale' => 'en']);
    }

    private function tool(array $attributes = []): ToolSalePost
    {
        return ToolSalePost::create(array_merge([
            'en_name' => 'Saddle', 'ar_name' => 'سرج', 'category' => 'saddlery_tack', 'condition' => 'used',
            'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'],
            'price' => 100, 'cover_photo' => 'tools-for-sale/covers/saddle.jpg', 'contact_number' => '91234567', 'status' => 'active',
        ], $attributes));
    }

    private function switchOn(int $cap = 3): void
    {
        $this->settings(['tools' => true], $cap);
    }

    private function settings(array $sections, int $cap = 3): void
    {
        $this->seedApiSettings(['currency_code' => 'OMR', 'currency_symbol' => 'OMR', 'mobile.auto_push' => json_encode(['sections' => $sections, 'daily_cap' => $cap])]);
        SiteSetting::resetMemo();
    }

    private function sendDue(): void
    {
        $this->artisan(SendDuePushCampaigns::class)->assertSuccessful();
    }

    public function test_every_section_points_at_things_that_exist(): void
    {
        $features = array_merge(...array_values(MobileFeatures::GROUPS));

        foreach (MobileAutoPush::SECTIONS as $section => [$model, $scope, $feature, $topic, $route]) {
            $this->assertTrue(method_exists($model, 'scope'.ucfirst($scope)), $section);
            $this->assertContains($feature, $features, $section);
            $this->assertContains($topic, MobileDevice::TOPICS, $section);
            $this->assertTrue($route === null ? in_array($feature, MobileScreens::keys(), true) : Route::has($route), $section);
            $this->assertNotSame('api.push.new_'.$section, __('api.push.new_'.$section, [], 'en'), $section);
            $this->assertNotSame('api.push.new_'.$section, __('api.push.new_'.$section, [], 'ar'), $section);
        }
    }

    public function test_nothing_is_queued_while_the_section_is_off(): void
    {
        $this->tool();

        $this->assertSame(0, PushCampaign::query()->count());
    }

    public function test_an_item_is_announced_once_when_it_goes_live_in_both_languages(): void
    {
        $this->switchOn();

        $tool = $this->tool(['status' => 'cancelled']);
        $this->assertSame(0, PushCampaign::query()->count());

        $tool->update(['status' => 'active']);
        $tool->update(['price' => 90]);
        $tool->update(['status' => 'cancelled']);
        $tool->update(['status' => 'active']);

        $campaign = PushCampaign::query()->sole();
        $this->assertSame(['tools', $tool->id, 'marketplace', 'scheduled'], [$campaign->source, $campaign->source_id, $campaign->topic, $campaign->status]);
        $this->assertSame(__('api.push.new_tools', [], 'ar'), $campaign->ar_title);
        $this->assertStringContainsString('سرج', (string) $campaign->ar_body);
        $this->assertStringContainsString('Saddle', (string) $campaign->en_body);
        $this->assertStringEndsWith("/tools-for-sale/{$tool->id}", (string) $campaign->action_url);
    }

    public function test_a_blog_post_published_for_later_is_announced_at_that_time(): void
    {
        foreach (['2026_09_07_130000_create_cms_categories_table', '2026_09_07_130002_create_cms_posts_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->settings(['blog' => true]);
        $this->device('all');

        $at = now()->addDay()->startOfMinute();
        CmsPost::create(['title' => ['en' => 'Hoof care', 'ar' => 'العناية بالحافر'], 'slug' => 'hoof-care', 'status' => 'published', 'published_at' => $at]);

        $campaign = PushCampaign::query()->sole();
        $this->assertSame(['Hoof care', 'العناية بالحافر', 'news'], [$campaign->en_body, $campaign->ar_body, $campaign->topic]);
        $this->assertTrue($at->equalTo($campaign->scheduled_at));

        $this->sendDue();
        Http::assertNothingSent();

        $this->travelTo($at->copy()->addMinute());
        $this->sendDue();
        Http::assertSentCount(1);
        $this->assertSame('sent', $campaign->refresh()->status);
    }

    public function test_it_goes_to_the_phones_that_want_it_and_is_listed_in_their_inbox(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->switchOn();

        $this->device('all');
        $this->device('racing-only', ['topics' => ['racing']]);
        $this->device('switched-off', ['enabled' => false]);
        // told by its own saved search: not told twice
        $this->device('searched');
        $this->postJson('/api/v1/push/searches', ['token' => 'ExponentPushToken[searched]', 'section' => 'tools', 'filters' => ['category' => 'saddlery_tack']])->assertCreated();

        $tool = $this->tool();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => count($r->data()) === 1 && $r[0]['to'] === 'ExponentPushToken[searched]');

        $this->sendDue();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => count($r->data()) === 1 && $r[0]['to'] === 'ExponentPushToken[all]' && isset($r[0]['data']['campaign']));

        $campaign = PushCampaign::query()->sole();
        $this->assertSame(['sent', 1, 1], [$campaign->status, $campaign->recipients, $campaign->accepted]);
        $this->withHeaders(['X-Push-Token' => 'ExponentPushToken[all]'])->getJson('/api/v1/push/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.url', $campaign->action_url);
        $this->assertSame($tool->id, $campaign->source_id);
    }

    public function test_a_post_removed_or_a_section_switched_off_before_sending_is_dropped(): void
    {
        Http::fake();
        $this->switchOn();
        $this->device('all');

        $this->tool()->update(['status' => 'cancelled']);
        $this->sendDue();
        $this->assertSame(0, PushCampaign::query()->count());

        $this->tool();
        $this->settings([]);
        $this->sendDue();
        $this->assertSame(0, PushCampaign::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_daily_limit_counts_automatic_and_written_campaigns_apart(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->switchOn(cap: 1);
        $device = $this->device('all');

        $written = PushCampaign::withoutEvents(fn () => PushCampaign::create(['en_title' => 'Offer', 'ar_title' => 'عرض', 'topic' => 'offers', 'status' => 'scheduled', 'scheduled_at' => now()]));
        (new SendPushCampaign($written->id))->handle(app(ExpoPush::class));
        $this->assertSame(1, PushDelivery::query()->where('mobile_device_id', $device->id)->count());

        $this->tool();
        $this->sendDue();
        $this->assertSame(2, PushDelivery::query()->where('mobile_device_id', $device->id)->count());

        $this->tool();
        $this->sendDue();
        $this->assertSame(2, PushDelivery::query()->where('mobile_device_id', $device->id)->count());
    }

    public function test_the_main_switch_stops_every_push_to_the_phone_and_keeps_its_topics(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);

        $this->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]', 'topics' => ['offers']])->assertOk();
        $this->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]', 'enabled' => false])->assertOk();
        $device = MobileDevice::query()->sole();
        $this->assertSame([false, ['offers']], [$device->enabled, $device->topics]);

        // booking alerts and saved searches go through here
        $this->assertSame([], app(ExpoPush::class)->send(MobileDevice::query()->get(), fn () => ['title' => 'x', 'body' => 'y']));
        Http::assertNothingSent();

        $this->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]'])->assertOk();
        $this->assertFalse($device->refresh()->enabled);

        $this->postJson('/api/v1/devices', ['token' => 'ExponentPushToken[a]', 'enabled' => true])->assertOk();
        $this->assertSame([$device->id], app(ExpoPush::class)->send(MobileDevice::query()->get(), fn () => ['title' => 'x', 'body' => 'y']));
    }
}
