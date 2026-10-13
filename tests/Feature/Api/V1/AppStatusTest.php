<?php

namespace Tests\Feature\Api\V1;

use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AppVersion;
use App\Support\MobileApp\MobileFeatures;
use Illuminate\Support\Carbon;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** GET /api/v1/app-status: maintenance, updates, feature switches and announcements; the posting guard. */
class AppStatusTest extends TestCase
{
    use PreparesMobileApi;

    private const IOS_1_0 = ['X-App-Platform' => 'ios', 'X-App-Version' => '1.0.0'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();
    }

    /** @param  array<string, mixed>  $settings  mobile.* keys as arrays */
    private function settings(array $settings): void
    {
        $this->seedApiSettings(array_map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value, $settings));
    }

    private function announcement(array $attributes = []): MobileAnnouncement
    {
        return MobileAnnouncement::withoutEvents(fn () => MobileAnnouncement::query()->create($attributes + [
            'en_title' => 'News', 'ar_title' => 'خبر', 'en_body' => 'Hello', 'ar_body' => 'مرحبا',
            'action_type' => 'none', 'frequency' => 'once', 'dismissible' => true, 'priority' => 0, 'is_active' => true,
        ]));
    }

    public function test_with_nothing_set_everything_is_on_and_nothing_is_asked(): void
    {
        $response = $this->getJson('/api/v1/app-status', self::IOS_1_0)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.maintenance', ['enabled' => false, 'message' => null])
            ->assertJsonPath('data.update.status', 'none')
            ->assertJsonPath('data.update.store_url', 'https://apps.apple.com/search?term=Muhra')
            ->assertJsonPath('data.update.store_app_url', null)
            ->assertJsonPath('data.announcements', [])
            ->assertJsonPath('data.client', ['platform' => 'ios', 'version' => '1.0.0']);

        $this->assertSame(array_fill_keys(MobileFeatures::keys(), 'on'), $response->json('data.features'));
        $this->assertContains('X-App-Version', $response->baseResponse->getVary());
    }

    public function test_a_feature_can_be_off_or_coming_soon_per_platform_and_version(): void
    {
        $this->settings(['mobile.features' => [
            'blog' => ['enabled' => false, 'when_off' => 'soon'],
            'videos' => ['enabled' => false],
            'shops' => ['enabled' => true, 'platforms' => ['android']],
            'events' => ['enabled' => true, 'min_version' => '1.2'],
        ]]);

        $features = fn (array $headers) => $this->getJson('/api/v1/app-status', $headers)->json('data.features');

        $ios = $features(self::IOS_1_0);
        $this->assertSame(['soon', 'hidden', 'hidden', 'hidden', 'on'], [$ios['blog'], $ios['videos'], $ios['shops'], $ios['events'], $ios['stables']]);

        $android = $features(['X-App-Platform' => 'android', 'X-App-Version' => '1.2.0']);
        $this->assertSame(['soon', 'on', 'on'], [$android['blog'], $android['shops'], $android['events']]);

        // A client that does not say (web preview): only the on/off switch applies.
        $unknown = $features([]);
        $this->assertSame(['soon', 'on', 'on'], [$unknown['blog'], $unknown['shops'], $unknown['events']]);
    }

    public function test_an_app_below_the_minimum_must_update_and_below_the_latest_may(): void
    {
        $this->settings(['mobile.updates' => [
            'ios' => ['latest_version' => '1.4.0', 'min_version' => '1.2', 'app_store_id' => 'id 6741234567'],
            'android' => ['latest_version' => '1.4.0', 'min_version' => ''],
            'messages' => ['required' => ['ar' => 'حدّث الآن']],
        ]]);

        $this->getJson('/api/v1/app-status', self::IOS_1_0)
            ->assertJsonPath('data.update', [
                'status' => 'required',
                'latest_version' => '1.4.0',
                'min_version' => '1.2',
                'store_url' => 'https://apps.apple.com/app/id6741234567',
                'store_app_url' => 'itms-apps://apps.apple.com/app/id6741234567',
                'message' => __('api.app.update_required', [], 'en'),
            ]);

        $this->getJson('/api/v1/app-status', self::IOS_1_0 + ['Accept-Language' => 'ar'])->assertJsonPath('data.update.message', 'حدّث الآن');
        $this->getJson('/api/v1/app-status', ['X-App-Platform' => 'ios', 'X-App-Version' => '1.2.0'])->assertJsonPath('data.update.status', 'optional');
        $this->getJson('/api/v1/app-status', ['X-App-Platform' => 'ios', 'X-App-Version' => '1.10.0'])->assertJsonPath('data.update.status', 'none');

        $this->getJson('/api/v1/app-status', ['X-App-Platform' => 'android', 'X-App-Version' => '1.0.0'])
            ->assertJsonPath('data.update.status', 'optional')
            ->assertJsonPath('data.update.min_version', null)
            ->assertJsonPath('data.update.store_url', 'https://play.google.com/store/apps/details?id=om.muhra.app')
            ->assertJsonPath('data.update.store_app_url', 'market://details?id=om.muhra.app');

        // No headers, a made-up platform or a malformed version: never told to update.
        $this->getJson('/api/v1/app-status')->assertJsonPath('data.update.status', 'none');
        $this->getJson('/api/v1/app-status', ['X-App-Platform' => 'windows', 'X-App-Version' => '1.0.0'])->assertJsonPath('data.update.status', 'none');
        $this->getJson('/api/v1/app-status', ['X-App-Platform' => 'ios', 'X-App-Version' => '1.0-beta'])
            ->assertJsonPath('data.update.status', 'none')
            ->assertJsonPath('data.client', ['platform' => 'ios', 'version' => null]);
    }

    public function test_versions_compare_number_by_number(): void
    {
        $this->assertTrue(AppVersion::lessThan('1.9.0', '1.10.0'));
        $this->assertSame(0, AppVersion::compare('1.2', '1.2.0'));
        $this->assertFalse(AppVersion::valid('v1.2'));
        $this->assertFalse(AppVersion::valid('1.2.3.4'));
    }

    public function test_maintenance_closes_the_app_with_the_admins_message_or_the_default(): void
    {
        $this->settings(['mobile.maintenance' => ['enabled' => true, 'messages' => ['en' => 'Back at 6 pm', 'ar' => '']]]);

        $this->getJson('/api/v1/app-status')->assertJsonPath('data.maintenance', ['enabled' => true, 'message' => 'Back at 6 pm']);
        $this->getJson('/api/v1/app-status', ['Accept-Language' => 'ar'])->assertJsonPath('data.maintenance.message', __('api.app.maintenance', [], 'ar'));
    }

    public function test_announcements_follow_their_audience_dates_and_priority(): void
    {
        Carbon::setTestNow('2026-10-13 10:00:00');

        $top = $this->announcement(['en_title' => 'Top', 'priority' => 5, 'image_path' => 'mobile/announcements/a.jpg',
            'action_type' => 'screen', 'action_screen' => 'horses_for_sale', 'ar_button_label' => 'تصفح']);
        $link = $this->announcement(['en_title' => 'Link', 'action_type' => 'url', 'action_url' => 'https://example.com/x', 'dismissible' => false]);
        $this->announcement(['en_title' => 'Off', 'is_active' => false]);
        $this->announcement(['en_title' => 'Later', 'starts_at' => now()->addHour()]);
        $this->announcement(['en_title' => 'Over', 'ends_at' => now()]);
        $this->announcement(['en_title' => 'Android', 'platforms' => ['android']]);
        $this->announcement(['en_title' => 'Arabic', 'locales' => ['ar']]);
        $this->announcement(['en_title' => 'Old apps', 'max_version' => '0.9']);
        $this->announcement(['en_title' => 'New apps', 'min_version' => '1.1']);
        $this->announcement(['en_title' => 'Blog', 'action_type' => 'screen', 'action_screen' => 'blog']);
        $this->settings(['mobile.features' => ['blog' => ['enabled' => false]]]);

        $this->getJson('/api/v1/app-status', self::IOS_1_0)
            ->assertJsonCount(2, 'data.announcements')
            ->assertJsonPath('data.announcements.0', [
                'id' => $top->id,
                'title' => 'Top',
                'body' => 'Hello',
                'image_url' => url('/storage/mobile/announcements/a.jpg'),
                'button' => ['label' => __('api.app.announcement_button', [], 'en'), 'url' => null, 'screen' => 'horses_for_sale'],
                'frequency' => 'once',
                'dismissible' => true,
                'priority' => 5,
                'updated_at' => $top->updated_at->toIso8601String(),
            ])
            ->assertJsonPath('data.announcements.1.id', $link->id)
            ->assertJsonPath('data.announcements.1.button', ['label' => 'Open', 'url' => 'https://example.com/x', 'screen' => null])
            ->assertJsonPath('data.announcements.1.dismissible', false);

        $arabic = $this->getJson('/api/v1/app-status', self::IOS_1_0 + ['Accept-Language' => 'ar'])->json('data.announcements');
        $this->assertSame(['خبر', 'خبر', 'خبر'], array_column($arabic, 'title'));
        $this->assertSame('تصفح', $arabic[0]['button']['label']);

        Carbon::setTestNow();
    }

    public function test_an_announcement_without_a_button_can_always_be_closed(): void
    {
        $this->announcement(['dismissible' => false]);

        $this->getJson('/api/v1/app-status')->assertJsonPath('data.announcements.0.dismissible', true);
    }

    public function test_posting_is_refused_while_its_feature_is_off_in_maintenance_or_below_the_minimum(): void
    {
        // With everything on, the request reaches the controller (which then asks for a verified phone).
        $this->postJson('/api/v1/horses-for-sale', [], self::IOS_1_0)->assertStatus(401)->assertJsonPath('code', 'verification_required');

        $this->settings(['mobile.features' => ['post_horse' => ['enabled' => false]]]);
        $this->postJson('/api/v1/horses-for-sale', [], self::IOS_1_0)->assertStatus(403)->assertJsonPath('code', 'feature_disabled');
        $this->postJson('/api/v1/tools-for-sale', [], self::IOS_1_0)->assertStatus(401);
        $this->postJson('/api/v1/verification/request', [], self::IOS_1_0)->assertStatus(422);

        $this->settings(['mobile.features' => array_fill_keys(['post_horse', 'post_tool', 'post_farrier', 'post_transfer'], ['enabled' => false])]);
        $this->postJson('/api/v1/verification/request', ['phone' => '91234567'], self::IOS_1_0)->assertStatus(403);

        $this->settings(['mobile.updates' => ['ios' => ['min_version' => '1.1']]]);
        $this->postJson('/api/v1/horses-for-sale', [], self::IOS_1_0)->assertStatus(426)->assertJsonPath('code', 'update_required');
        $this->postJson('/api/v1/horses-for-sale', [], ['X-App-Platform' => 'ios', 'X-App-Version' => '1.1.0'])->assertStatus(401);

        $this->settings(['mobile.maintenance' => ['enabled' => true]]);
        $this->postJson('/api/v1/horses-for-sale', [], self::IOS_1_0)->assertStatus(503)->assertJsonPath('code', 'maintenance');
    }
}
