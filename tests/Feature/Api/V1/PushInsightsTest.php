<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\PushCampaignResource\Pages\CreatePushCampaign;
use App\Filament\Resources\PushCampaignResource\Pages\ListPushCampaigns;
use App\Filament\Resources\PushCampaignResource\Pages\ViewPushCampaign;
use App\Filament\Resources\PushCampaignResource\Widgets\PushStatsOverview;
use App\Models\AppEvent;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Support\MobileApp\PushInsights;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** Admin → Mobile app → Notifications: the insight widgets, the campaign report, estimated reach and pause. */
class PushInsightsTest extends TestCase
{
    use PreparesStableBookings;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->prepareStableBookings(['sms.driver' => 'demo']);

        foreach ([
            '2026_09_11_145142_create_personal_access_tokens_table',
            '2026_11_04_000002_create_push_campaigns_tables',
            '2026_11_07_000002_add_source_to_push_campaigns',
            '2026_11_12_000001_add_controls_to_push_campaigns',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
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

    private function sentCampaign(): PushCampaign
    {
        $campaign = PushCampaign::create(['en_title' => 'Race day', 'ar_title' => 'يوم السباق']);
        $campaign->forceFill(['status' => 'sent', 'sent_at' => now()->subHours(3), 'recipients' => 3, 'accepted' => 2, 'opened' => 1])->saveQuietly();

        return $campaign;
    }

    public function test_the_figures_count_reach_sends_and_opens(): void
    {
        $a = $this->device('a', ['topics' => ['news']]);
        $b = $this->device('b', ['platform' => 'android']);
        $this->device('off', ['enabled' => false]);
        $campaign = $this->sentCampaign();
        PushDelivery::create(['push_campaign_id' => $campaign->id, 'mobile_device_id' => $a->id, 'opened_at' => now()->subHours(2)]);
        PushDelivery::create(['push_campaign_id' => $campaign->id, 'mobile_device_id' => $b->id]);

        $summary = PushInsights::summary();
        $this->assertSame([2, 2, 1, 3, 2, 1], [$summary['reachable'], $summary['sent'], $summary['opened'], $summary['targeted'], $summary['accepted'], $summary['campaign_opens']]);
        $this->assertSame(2, array_sum(PushInsights::daily()['sent']));
        $this->assertSame(1, array_sum(PushInsights::daily()['opened']));

        $audience = PushInsights::audience();
        $this->assertEquals(['ios' => 1, 'android' => 1], $audience['platforms']);
        $this->assertSame([2, 1], [$audience['topics']['news'], $audience['topics']['racing']]);
        $this->assertSame(1, PushInsights::campaignOpens($campaign)['opened'][1]);
        $this->assertSame(50.0, PushInsights::rate(1, 2));
        $this->assertNull(PushInsights::change(5, 0));
    }

    public function test_installs_count_each_phones_latest_permission(): void
    {
        $event = fn (string $install, string $name, array $properties = [], int $daysAgo = 0) => AppEvent::query()->create([
            'install_id' => $install, 'name' => $name, 'properties' => $properties ?: null, 'occurred_at' => now()->subDays($daysAgo),
        ]);
        $event('a', 'push_permission', ['status' => 'undetermined'], 2);
        $event('a', 'push_permission', ['status' => 'granted'], 1); // asked on Home: counts once, as allowed
        $event('b', 'push_permission', ['status' => 'denied']);
        $event('c', 'app_launched');
        $event('old', 'push_permission', ['status' => 'granted'], 40);

        $this->assertSame(['installs' => 3, 'granted' => 1, 'denied' => 1, 'undetermined' => 0], PushInsights::permissions());
    }

    public function test_lapsed_installs_opened_the_app_before_but_not_in_the_last_30_days(): void
    {
        $event = fn (string $install, int $daysAgo) => AppEvent::query()->create(['install_id' => $install, 'name' => 'app_launched', 'occurred_at' => now()->subDays($daysAgo)]);
        $event('stayed', 45);
        $event('stayed', 2);
        $event('left', 40);
        $event('long-gone', 90);

        $this->assertSame(['uninstalled' => 0, 'lapsed' => 1], PushInsights::losses());
    }

    public function test_the_page_shows_the_widgets_and_the_report_to_admins_only(): void
    {
        $campaign = $this->sentCampaign();
        $this->assertFalse(PushStatsOverview::canView());

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());

        Livewire::test(ListPushCampaigns::class)->assertSuccessful()->assertSeeLivewire(PushStatsOverview::class);
        Livewire::test(PushStatsOverview::class)->assertSuccessful()->assertSee(__('admin_widgets.push.reachable'))->assertSee(__('admin_widgets.push.installs'))->assertSee(__('admin_widgets.push.uninstalled'));
        Livewire::test(ViewPushCampaign::class, ['record' => $campaign->getKey()])->assertSuccessful();
        Livewire::test(ListPushCampaigns::class, ['activeTab' => 'scheduled'])->assertCanNotSeeTableRecords([$campaign]);
    }

    public function test_the_form_shows_the_estimated_reach(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        $this->device('a');
        $this->device('b', ['platform' => 'android']);

        Livewire::test(CreatePushCampaign::class)
            ->assertSee(trans_choice('admin_widgets.push.reach_phones', 2, ['count' => 2]))
            ->fillForm(['platforms' => ['android']])
            ->assertSee(trans_choice('admin_widgets.push.reach_phones', 1, ['count' => 1]));
    }

    public function test_a_scheduled_campaign_can_be_paused(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        $campaign = PushCampaign::create(['en_title' => 'Offer', 'ar_title' => 'عرض', 'scheduled_at' => now()->addDay()]);
        $this->assertSame('scheduled', $campaign->status);

        Livewire::test(ListPushCampaigns::class)
            ->callTableAction('pause', $campaign)
            ->assertNotified(__('admin_widgets.push.paused'));

        $this->assertSame(['draft', null], [$campaign->refresh()->status, $campaign->scheduled_at]);
    }
}
