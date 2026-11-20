<?php

namespace Tests\Feature;

use App\Filament\Resources\AdResource\Pages\EditAd;
use App\Filament\Resources\AdResource\Pages\ListAds;
use App\Filament\Resources\AdResource\Widgets\AdStatsOverview;
use App\Filament\Resources\AdResource\Widgets\TopAds;
use App\Models\Ad;
use App\Models\BookingSlot;
use App\Support\Ads\AdInsights;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** Admin → Promotions: impressions and clicks are counted on the website and in the app, and shown in the widgets. */
class AdInsightsTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private int $zone;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->zone = $this->row('ad_zones', ['name' => $this->both('Horses', 'الخيول'), 'slug' => 'horses']);
        $this->seedApiSettings(['mobile.ads' => json_encode(['horses' => $this->zone])]);
    }

    private function ad(string $name = 'Sponsor'): int
    {
        return $this->row('ads', ['zone_id' => $this->zone, 'customer_name' => $name, 'image_path' => 'ads/one.jpg', 'target_url' => 'https://sponsor.example']);
    }

    private function stats(int $ad, string $source): array
    {
        return (array) DB::table('ad_daily_stats')->where(['ad_id' => $ad, 'source' => $source])->first(['impressions', 'clicks']);
    }

    public function test_serving_and_clicking_count_impressions_and_clicks_per_source(): void
    {
        $ad = $this->ad();

        $this->getJson('/api/v1/ads/horses')->assertOk();
        $this->getJson('/api/v1/ads/horses')->assertOk();
        $this->get(route('promo.click', ['ad' => $ad, 'src' => 'app']))->assertRedirect('https://sponsor.example');
        $this->get(route('promo.click', $ad))->assertRedirect('https://sponsor.example');

        $this->assertEquals(['impressions' => 2, 'clicks' => 1], $this->stats($ad, 'app'));
        $this->assertEquals(['impressions' => 0, 'clicks' => 1], $this->stats($ad, 'web'));
        $this->assertSame(2, DB::table('ads')->where('id', $ad)->value('clicks'));

        $summary = AdInsights::summary();
        $this->assertSame([2, 2, 1], [$summary['impressions'], $summary['clicks'], $summary['running']]);
        $this->assertSame(2, array_sum(AdInsights::daily($ad)['impressions']));
        $this->assertSame(['impressions' => 2, 'clicks' => 1], AdInsights::sources()['app']);
        $this->assertSame([['id' => $ad, 'name' => 'Sponsor', 'impressions' => 2, 'clicks' => 2]], AdInsights::top());
        $this->assertSame(100.0, AdInsights::rate(2, 2));
    }

    public function test_the_list_and_edit_pages_show_the_widgets_to_admins_only(): void
    {
        $ad = $this->ad();
        DB::table('ad_daily_stats')->insert(['ad_id' => $ad, 'date' => now(BookingSlot::timezone())->toDateString(), 'source' => 'web', 'impressions' => 40, 'clicks' => 3]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertFalse(AdStatsOverview::canView());

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });

        Livewire::test(ListAds::class)->assertSuccessful()->assertSeeLivewire(AdStatsOverview::class)
            ->assertTableColumnStateSet('impressions_total', 40, Ad::find($ad));
        Livewire::test(AdStatsOverview::class)->assertSuccessful()->assertSee(__('admin_widgets.ads.impressions'))->assertSee('7.5%');
        Livewire::test(TopAds::class)->assertSuccessful()->assertSee('Sponsor');
        Livewire::test(EditAd::class, ['record' => $ad])->assertSuccessful()->assertSeeLivewire(AdStatsOverview::class);
    }
}
