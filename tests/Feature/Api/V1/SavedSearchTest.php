<?php

namespace Tests\Feature\Api\V1;

use App\Models\MobileDevice;
use App\Models\SiteSetting;
use App\Models\ToolSalePost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** "Tell me about new ones like these": a phone saves a board's filters, and posts going live that match are pushed to it. */
class SavedSearchTest extends TestCase
{
    use PreparesMobileApi;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi(['currency_code' => 'OMR', 'currency_symbol' => 'OMR']);
        $this->ids = $this->seedLocations();

        // only what the phones' table points at
        Schema::create('users', fn (Blueprint $table) => $table->id());

        foreach (['2026_09_11_145142_create_personal_access_tokens_table', '2026_09_23_000001_create_customers_tables', '2026_11_04_000001_create_mobile_devices_table', '2026_11_07_000001_add_enabled_to_mobile_devices', '2026_11_04_000002_create_push_campaigns_tables', '2026_11_07_000002_add_source_to_push_campaigns', '2026_11_04_000003_create_push_saved_searches_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        foreach (['phone', 'other'] as $name) {
            MobileDevice::create(['expo_token' => "ExponentPushToken[{$name}]", 'locale' => $name === 'phone' ? 'ar' : 'en']);
        }
    }

    private function tool(array $attributes = []): ToolSalePost
    {
        return ToolSalePost::create(array_merge([
            'en_name' => 'Saddle', 'ar_name' => 'سرج', 'category' => 'saddlery_tack', 'condition' => 'used',
            'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'],
            'price' => 100, 'cover_photo' => 'tools-for-sale/covers/saddle.jpg', 'contact_number' => '91234567', 'status' => 'active',
        ], $attributes));
    }

    public function test_a_phone_saves_lists_and_removes_its_searches(): void
    {
        $save = fn (array $filters, string $token = 'phone') => $this->postJson('/api/v1/push/searches', ['token' => "ExponentPushToken[{$token}]", 'section' => 'tools', 'filters' => $filters]);

        $save(['category' => 'saddlery_tack'], 'unknown')->assertNotFound();
        $this->postJson('/api/v1/push/searches', ['token' => 'ExponentPushToken[phone]', 'section' => 'boats', 'filters' => []])->assertUnprocessable();

        $id = $save(['category' => 'saddlery_tack', 'city_id' => $this->ids['seeb'], 'price_max' => 150, 'sort' => 'price_asc', 'page' => 2])->assertCreated()->json('data.id');
        $this->assertSame($id, $save(['price_max' => '150', 'city_id' => (string) $this->ids['seeb'], 'category' => 'saddlery_tack'])->json('data.id'));
        $save([])->assertCreated();

        $this->withHeaders(['X-Push-Token' => 'ExponentPushToken[phone]'])->getJson('/api/v1/push/searches')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', __('api.push.all_tools', [], 'en'))
            ->assertJsonPath('data.1.label', __('tools-for-sale.categories.saddlery_tack', [], 'en').' · Seeb · up to '.SiteSetting::formatCurrency(150))
            ->assertJsonPath('data.1.filters', ['category' => 'saddlery_tack', 'city_id' => (string) $this->ids['seeb'], 'price_max' => '150']);

        $this->withHeaders(['X-Push-Token' => 'ExponentPushToken[other]'])->deleteJson("/api/v1/push/searches/{$id}")->assertNotFound();
        $this->withHeaders(['X-Push-Token' => 'ExponentPushToken[phone]'])->deleteJson("/api/v1/push/searches/{$id}")->assertOk();
        $this->withHeaders(['X-Push-Token' => 'ExponentPushToken[phone]'])->getJson('/api/v1/push/searches')->assertJsonCount(1, 'data');

        foreach (range(2, 10) as $price) {
            $save(['price_max' => $price])->assertCreated();
        }
        $save(['price_max' => 11])->assertUnprocessable()->assertJsonValidationErrors('section');
    }

    public function test_a_post_going_live_is_pushed_to_the_matching_searches_once(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->postJson('/api/v1/push/searches', ['token' => 'ExponentPushToken[phone]', 'section' => 'tools', 'filters' => ['category' => 'grooming']])->assertCreated();
        $this->postJson('/api/v1/push/searches', ['token' => 'ExponentPushToken[phone]', 'section' => 'tools', 'filters' => ['price_max' => 50]])->assertCreated();
        $this->postJson('/api/v1/push/searches', ['token' => 'ExponentPushToken[other]', 'section' => 'tools', 'filters' => ['category' => 'transport_equipment']])->assertCreated();

        // matches both of the phone's searches: one push; not live (here: cancelled, like one awaiting review): none until it is
        $brush = $this->tool(['en_name' => 'Brush', 'ar_name' => 'فرشاة', 'category' => 'grooming', 'price' => 5, 'status' => 'cancelled']);
        Http::assertNothingSent();
        $brush->update(['status' => 'active']);
        $brush->update(['price' => 6]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => count($request->data()) === 1
            && $request[0]['to'] === 'ExponentPushToken[phone]'
            && $request[0]['title'] === __('api.push.new_tools', [], 'ar')
            && $request[0]['body'] === 'فرشاة · '.SiteSetting::formatCurrency(5)
            && $request[0]['data'] === ['path' => "/tools/{$brush->id}"]);

        $this->tool(['category' => 'saddlery_tack', 'price' => 400]);
        Http::assertSentCount(1);
    }
}
