<?php

namespace Tests\Feature;

use App\Models\HorseSalePost;
use App\Models\ToolSalePost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/**
 * The website's sale boards filter through the same model scopes as the mobile API
 * (HorseSalePost/ToolSalePost::filteredBy() and sortedBy()).
 */
class SaleListingFiltersTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesSiteLayout;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
        $this->prepareMobileApi();

        // Seo::forSlug() looks for a CMS page with the board's slug; none is published here.
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('status');
            $table->timestamp('published_at')->nullable();
        });

        $this->ids = $this->seedLocations();
        $this->ids['arabian'] = DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']);
        $this->ids['pony'] = DB::table('types')->insertGetId(['en_name' => 'Pony', 'ar_name' => 'قزم']);
        $this->ids['mare'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);
    }

    public function test_the_horses_board_filters_and_sorts(): void
    {
        $this->horse(['en_name' => 'Desert Star', 'price' => 900]);
        $this->horse(['en_name' => 'Little Pebble', 'type_id' => $this->ids['pony'], 'price' => 300]);
        $this->horse(['en_name' => 'Gone Already', 'status' => 'sold']);

        $this->get(route('horses-for-sale.index', ['type_id' => $this->ids['pony']]))
            ->assertOk()
            ->assertSee('Little Pebble')
            ->assertDontSee('Desert Star')
            ->assertDontSee('Gone Already');

        $this->get(route('horses-for-sale.index', ['sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Little Pebble', 'Desert Star']);

        // As before: a non-numeric id is no filter at all.
        $this->get(route('horses-for-sale.index', ['type_id' => 'abc']))
            ->assertOk()
            ->assertSee('Little Pebble')
            ->assertSee('Desert Star');
    }

    public function test_the_tools_board_filters_and_ignores_unknown_categories(): void
    {
        $this->tool(['en_name' => 'Soft Brush', 'category' => 'grooming']);
        $this->tool(['en_name' => 'Old Saddle']);

        $this->get(route('tools-for-sale.index', ['category' => 'grooming']))
            ->assertOk()
            ->assertSee('Soft Brush')
            ->assertDontSee('Old Saddle');

        $this->get(route('tools-for-sale.index', ['category' => 'boats']))
            ->assertOk()
            ->assertSee('Soft Brush')
            ->assertSee('Old Saddle');
    }

    private function horse(array $attributes): HorseSalePost
    {
        return HorseSalePost::create(array_merge([
            'ar_name' => 'حصان',
            'type_id' => $this->ids['arabian'],
            'gender_id' => $this->ids['mare'],
            'country_id' => $this->ids['oman'],
            'region_id' => $this->ids['muscat'],
            'city_id' => $this->ids['seeb'],
            'price' => 1000,
            'cover_photo' => 'horse-sale/covers/x.jpg',
            'contact_number' => '99887766',
            'status' => 'active',
        ], $attributes));
    }

    private function tool(array $attributes): ToolSalePost
    {
        return ToolSalePost::create(array_merge([
            'ar_name' => 'أداة',
            'category' => 'saddlery_tack',
            'condition' => 'used',
            'country_id' => $this->ids['oman'],
            'region_id' => $this->ids['muscat'],
            'city_id' => $this->ids['seeb'],
            'price' => 100,
            'cover_photo' => 'tools-for-sale/covers/x.jpg',
            'contact_number' => '91234567',
            'status' => 'active',
        ], $attributes));
    }
}
