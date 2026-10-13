<?php

namespace Tests\Feature\Api\V1;

use App\Models\ToolSalePost;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

class ToolsForSaleTest extends TestCase
{
    use PreparesMobileApi;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi(['currency_code' => 'OMR', 'currency_symbol' => 'OMR', 'currency_icon' => 'branding/rial.svg']);

        $this->ids = $this->seedLocations();
    }

    public function test_the_list_shows_only_active_tools_with_translated_category_and_condition(): void
    {
        $saddle = $this->tool(['en_name' => 'Saddle', 'brand' => 'Stübben', 'price' => '45.25']);
        $this->tool(['en_name' => 'Cancelled', 'status' => 'cancelled']);

        $this->getJson('/api/v1/tools-for-sale')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $saddle->id)
            ->assertJsonPath('data.0.category', ['value' => 'saddlery_tack', 'label' => __('tools-for-sale.categories.saddlery_tack', [], 'en')])
            ->assertJsonPath('data.0.condition', ['value' => 'used', 'label' => __('tools-for-sale.conditions.used', [], 'en')])
            ->assertJsonPath('data.0.brand', 'Stübben')
            ->assertJsonPath('data.0.price', [
                'amount' => '45.250',
                'currency' => ['code' => 'OMR', 'symbol' => 'OMR', 'icon_url' => url('/storage/branding/rial.svg')],
            ])
            ->assertJsonPath('data.0.city.name', 'Seeb');

        $this->getJson('/api/v1/tools-for-sale', ['Accept-Language' => 'ar'])
            ->assertJsonPath('data.0.name', 'سرج')
            ->assertJsonPath('data.0.category.label', __('tools-for-sale.categories.saddlery_tack', [], 'ar'))
            ->assertJsonPath('data.0.city.name', 'السيب');
    }

    public function test_the_list_filters_like_the_website(): void
    {
        $brush = $this->tool(['en_name' => 'Brush', 'category' => 'grooming', 'condition' => 'new', 'price' => 5]);
        $saddle = $this->tool(['en_name' => 'Saddle', 'city_id' => $this->ids['bawshar'], 'price' => 300]);
        $trailer = $this->tool(['en_name' => 'Trailer', 'category' => 'transport_equipment', 'price' => 4000]);

        $this->assertListIds(['category' => 'grooming'], [$brush->id]);
        $this->assertListIds(['condition' => 'used'], [$trailer->id, $saddle->id]);
        $this->assertListIds(['city_id' => $this->ids['bawshar']], [$saddle->id]);
        $this->assertListIds(['price_max' => 300, 'sort' => 'price_desc'], [$saddle->id, $brush->id]);
        $this->assertListIds(['country_id' => $this->ids['oman'], 'sort' => 'price_asc'], [$brush->id, $saddle->id, $trailer->id]);

        $this->getJson('/api/v1/tools-for-sale?category=boats')->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_a_tool_shows_its_page_details_and_inactive_ones_are_404(): void
    {
        $tool = $this->tool(['description_en' => 'Barely used.', 'description_ar' => 'استخدام خفيف.']);
        $sold = $this->tool(['status' => 'sold']);

        $this->getJson("/api/v1/tools-for-sale/{$tool->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Saddle')
            ->assertJsonPath('data.description', 'Barely used.')
            ->assertJsonPath('data.region.name', 'Muscat')
            ->assertJsonPath('data.cover_photo_url', url('/storage/tools-for-sale/covers/saddle.jpg'))
            ->assertJsonPath('data.contact_number', '91234567')
            ->assertJsonPath('data.share_url', route('tools-for-sale.show', $tool->id));

        $this->getJson("/api/v1/tools-for-sale/{$tool->id}", ['Accept-Language' => 'ar'])->assertJsonPath('data.description', 'استخدام خفيف.');

        $this->getJson("/api/v1/tools-for-sale/{$sold->id}")->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<int>  $expected
     */
    private function assertListIds(array $query, array $expected): void
    {
        $ids = $this->getJson('/api/v1/tools-for-sale?'.http_build_query($query))->assertOk()->json('data.*.id');

        $this->assertSame($expected, $ids, 'filters: '.http_build_query($query));
    }

    private function tool(array $attributes = []): ToolSalePost
    {
        return ToolSalePost::create(array_merge([
            'en_name' => 'Saddle',
            'ar_name' => 'سرج',
            'category' => 'saddlery_tack',
            'condition' => 'used',
            'country_id' => $this->ids['oman'],
            'region_id' => $this->ids['muscat'],
            'city_id' => $this->ids['seeb'],
            'price' => 100,
            'cover_photo' => 'tools-for-sale/covers/saddle.jpg',
            'contact_number' => '91234567',
            'status' => 'active',
        ], $attributes));
    }
}
