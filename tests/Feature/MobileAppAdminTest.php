<?php

namespace Tests\Feature;

use App\Filament\Pages\MobileApp\MobileAppSettings;
use App\Filament\Resources\MobileAnnouncementResource\Pages\CreateMobileAnnouncement;
use App\Filament\Resources\MobileAnnouncementResource\Pages\ListMobileAnnouncements;
use App\Models\MobileAnnouncement;
use App\Models\SiteSetting;
use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\AppLinks;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileMaintenance;
use App\Support\MobileApp\MobilePosting;
use App\Support\MobileApp\MobileUpdates;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin → Mobile app: the settings page (features, updates, maintenance, posting) and the announcements. */
class MobileAppAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });
        SiteSetting::clearCache();

        foreach ([
            '2025_10_03_170348_create_activity_log_table',
            '2025_10_03_170349_add_event_column_to_activity_log_table',
            '2025_10_03_170350_add_batch_uuid_column_to_activity_log_table',
            '2026_10_13_120000_create_mobile_announcements_table',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_settings_page_saves_features_versions_and_maintenance_and_logs_the_change(): void
    {
        Livewire::test(MobileAppSettings::class)
            ->assertSuccessful()
            ->assertFormSet(['features.blog.enabled' => true, 'features.blog.platforms' => ['ios', 'android'], 'features.blog.when_off' => 'hidden'])
            ->fillForm([
                'features.blog.enabled' => false,
                'features.blog.when_off' => 'soon',
                'features.events.platforms' => ['android'],
                'features.events.min_version' => '1.2.0',
                'updates.ios.latest_version' => '1.3.0',
                'updates.ios.min_version' => '1.1.0',
                'updates.ios.app_store_id' => '6741234567',
                'maintenance.enabled' => true,
                'maintenance.messages.en' => 'Back soon',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $ios = new AppClient('ios', '1.0.0');
        $this->assertSame('soon', MobileFeatures::forClient($ios)['blog']);
        $this->assertSame('hidden', MobileFeatures::forClient($ios)['events']);
        $this->assertSame('on', MobileFeatures::forClient(new AppClient('android', '1.2.0'))['events']);
        $this->assertSame('required', MobileUpdates::forClient($ios)['status']);
        $this->assertSame('https://apps.apple.com/app/id6741234567', MobileUpdates::forClient($ios)['store_url']);
        $this->assertSame('Back soon', MobileMaintenance::forClient()['message']);

        $log = DB::table('activity_log')->where('log_name', 'mobile_app')->sole();
        $properties = json_decode($log->properties, true);
        $this->assertTrue($properties['old']['features']['blog']['enabled']);
        $this->assertFalse($properties['attributes']['features']['blog']['enabled']);
    }

    public function test_a_minimum_above_the_latest_version_or_a_malformed_version_is_refused(): void
    {
        Livewire::test(MobileAppSettings::class)
            ->fillForm(['updates.android.latest_version' => '1.2.0', 'updates.android.min_version' => '1.3.0', 'features.blog.min_version' => 'v2'])
            ->call('save')
            ->assertHasFormErrors(['updates.android.min_version', 'features.blog.min_version']);

        $this->assertNull(SiteSetting::get('mobile.updates'));
    }

    public function test_the_posting_tab_turns_on_review_and_sets_the_daily_limit(): void
    {
        Livewire::test(MobileAppSettings::class)
            ->assertFormSet(['posting.review' => false, 'posting.daily_limit' => 5])
            ->fillForm(['posting.daily_limit' => 0])
            ->call('save')
            ->assertHasFormErrors(['posting.daily_limit'])
            ->fillForm(['posting.review' => true, 'posting.daily_limit' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['review' => true, 'daily_limit' => 3], MobilePosting::settings());
        $this->assertSame('pending', MobilePosting::newPostStatus());

        $log = DB::table('activity_log')->where('log_name', 'mobile_app')->sole();
        $this->assertTrue(json_decode($log->properties, true)['attributes']['posting']['review']);
    }

    public function test_the_website_links_tab_stores_the_apple_team_and_android_fingerprints(): void
    {
        $fingerprint = '14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5';

        Livewire::test(MobileAppSettings::class)
            ->fillForm(['links.apple_team_id' => 'ABC', 'links.android_fingerprints' => 'nope'])
            ->call('save')
            ->assertHasFormErrors(['links.apple_team_id', 'links.android_fingerprints'])
            ->fillForm(['links.apple_team_id' => ' abcde12345 ', 'links.android_fingerprints' => strtolower($fingerprint)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['apple_team_id' => 'ABCDE12345', 'android_fingerprints' => [$fingerprint]], AppLinks::settings());

        Livewire::test(MobileAppSettings::class)->assertFormSet(['links.android_fingerprints' => $fingerprint]);
    }

    public function test_an_admin_can_create_an_announcement_and_one_that_cannot_be_closed_needs_a_button(): void
    {
        $form = [
            'en_title' => 'New: tools', 'ar_title' => 'جديد: الأدوات',
            'action_type' => 'none', 'platforms' => ['ios', 'android'], 'frequency' => 'once', 'priority' => 1,
            'dismissible' => false, 'is_active' => true,
        ];

        Livewire::test(CreateMobileAnnouncement::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasFormErrors(['dismissible']);

        Livewire::test(CreateMobileAnnouncement::class)
            ->fillForm(['action_type' => 'screen', 'action_screen' => 'tools_for_sale', 'starts_at' => '2026-10-20 09:00'] + $form)
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = MobileAnnouncement::query()->sole();
        $this->assertSame('tools_for_sale', $announcement->action_screen);
        // typed in Muscat time, stored in UTC
        $this->assertSame('2026-10-20 05:00:00', $announcement->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('scheduled', $announcement->status());

        Livewire::test(ListMobileAnnouncements::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$announcement]);
    }
}
