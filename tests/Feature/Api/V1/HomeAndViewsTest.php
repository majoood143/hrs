<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

class HomeAndViewsTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private const PHONE = 'okhttp/4.12.0';

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->ids = $this->seedLocations();
        $this->ids['type'] = DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']);
        $this->ids['gender'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);
    }

    public function test_home_has_the_newest_of_each_section_and_the_homepage_promos(): void
    {
        $zone = $this->row('ad_zones', ['name' => $this->both('Home', 'الرئيسية'), 'slug' => 'home']);
        $this->row('cms_pages', ['slug' => 'home', 'is_homepage' => true, 'content' => json_encode([
            ['type' => 'hero', 'data' => []],
            ['type' => 'ads_area', 'data' => ['zone_id' => $zone, 'count' => 1]],
        ])]);
        $ad = $this->row('ads', ['zone_id' => $zone, 'image_path' => 'ads/one.jpg', 'target_url' => 'https://sponsor.example', 'customer_name' => 'Sponsor', 'order' => 1]);
        $this->row('ads', ['zone_id' => $zone, 'image_path' => 'ads/two.jpg', 'order' => 2]);
        $this->row('ads', ['zone_id' => null, 'image_path' => 'ads/other-zone.jpg', 'order' => 0]);
        $this->row('ads', ['zone_id' => $zone, 'image_path' => 'ads/expired.jpg', 'ends_at' => now()->subDay(), 'order' => 0]);

        $horse = $this->row('horse_sale_posts', [
            'en_name' => 'Star', 'ar_name' => 'نجمة', 'type_id' => $this->ids['type'], 'gender_id' => $this->ids['gender'],
            'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'],
            'price' => 100, 'cover_photo' => 'x.jpg', 'contact_number' => '1', 'status' => 'active',
        ]);
        $this->row('transfer_posts', ['type' => 'offer', 'transfer_date' => now()->addDay()->toDateString(), 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'status' => 'active']);
        $this->row('events', ['title' => $this->both('Show', 'عرض'), 'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->row('cms_posts', ['title' => $this->both('News', 'خبر'), 'slug' => 'news', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.ads', [[
                'id' => $ad, 'media_type' => 'image', 'media_url' => url('/storage/ads/one.jpg'),
                'alt' => 'Sponsor', 'click_url' => route('promo.click', $ad),
            ]])
            ->assertJsonPath('data.horses.0.id', $horse)
            ->assertJsonPath('data.tools', [])
            ->assertJsonPath('data.transfers.0.to.city.name', 'Bawshar')
            ->assertJsonPath('data.events.0.title', 'Show')
            ->assertJsonPath('data.posts.0.slug', 'news');
    }

    public function test_home_shows_no_promos_when_the_website_homepage_has_none(): void
    {
        $this->row('cms_pages', ['slug' => 'home', 'is_homepage' => true, 'content' => json_encode([['type' => 'hero', 'data' => []]])]);
        $this->row('ads', ['image_path' => 'ads/one.jpg']);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.ads', []);
    }

    public function test_home_leaves_out_the_sections_switched_off_for_this_app(): void
    {
        $this->row('transfer_posts', ['type' => 'offer', 'transfer_date' => now()->addDay()->toDateString(), 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'status' => 'active']);
        $this->row('events', ['title' => $this->both('Show', 'عرض'), 'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->seedApiSettings(['mobile.features' => json_encode([
            'transfer_board' => ['enabled' => false],
            'events' => ['enabled' => true, 'platforms' => ['android']],
        ])]);

        $this->getJson('/api/v1/home', ['X-App-Platform' => 'ios', 'X-App-Version' => '1.0.0'])
            ->assertOk()
            ->assertJsonPath('data.transfers', [])
            ->assertJsonPath('data.events', []);

        $this->getJson('/api/v1/home', ['X-App-Platform' => 'android', 'X-App-Version' => '1.0.0'])
            ->assertJsonPath('data.transfers', [])
            ->assertJsonPath('data.events.0.title', 'Show');
    }

    public function test_a_view_from_the_app_is_counted_but_never_by_a_get(): void
    {
        $clinic = $this->row('clinics', ['en_name' => 'Clinic', 'slug' => 'clinic', 'is_active' => true]);

        $this->getJson('/api/v1/clinics/clinic')->assertOk();
        $this->assertSame(0, (int) DB::table('clinics')->value('views_count'));

        $this->withHeader('User-Agent', self::PHONE)
            ->postJson('/api/v1/views/clinics/clinic')
            ->assertOk()
            ->assertJson(['counted' => true, 'views_count' => 1]);

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->postJson('/api/v1/views/clinics/clinic')
            ->assertOk()
            ->assertJson(['counted' => false, 'views_count' => 1]);

        $event = $this->row('events', ['title' => $this->both('Show', 'عرض'), 'date' => now()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->withHeader('User-Agent', self::PHONE)->postJson("/api/v1/views/events/{$event}")->assertJson(['views_count' => 1]);

        $this->assertSame(1, (int) DB::table('clinics')->where('id', $clinic)->value('views_count'));
    }

    public function test_only_visible_listings_of_known_sections_can_be_counted(): void
    {
        $this->row('clinics', ['en_name' => 'Closed', 'slug' => 'closed', 'is_active' => false]);
        $this->row('stables', ['en_name' => 'Stable', 'slug' => 'stable']);

        $this->withHeader('User-Agent', self::PHONE)->postJson('/api/v1/views/clinics/closed')->assertNotFound();
        $this->withHeader('User-Agent', self::PHONE)->postJson('/api/v1/views/stables/stable')->assertNotFound();
        $this->withHeader('User-Agent', self::PHONE)->postJson('/api/v1/views/users/1')->assertNotFound();
        $this->getJson('/api/v1/views/clinics/closed')->assertNotFound();
    }
}
