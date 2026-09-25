<?php

namespace Tests\Feature;

use App\Models\ToolSalePost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

class NegotiablePriceTest extends TestCase
{
    use PreparesSiteLayout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();

        // Seo::forSlug() looks for a CMS page with the board's slug; none is published here.
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('status');
            $table->timestamp('published_at')->nullable();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id');
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id');
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->timestamps();
        });

        Schema::create('tool_sale_posts', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('category');
            $table->string('condition');
            $table->string('brand')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('cover_photo')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('contact_number');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_30_000001_add_views_count_to_listings.php'))->up();
        (require database_path('migrations/2026_09_30_000003_add_price_negotiable_to_sale_posts.php'))->up();

        DB::table('countries')->insert(['id' => 1, 'en_name' => 'Oman', 'ar_name' => 'عمان']);
        DB::table('regions')->insert(['id' => 1, 'country_id' => 1, 'en_name' => 'Muscat', 'ar_name' => 'مسقط']);
        DB::table('cities')->insert(['id' => 1, 'region_id' => 1, 'en_name' => 'Seeb', 'ar_name' => 'السيب']);
    }

    public function test_a_negotiable_tool_shows_the_badge_on_its_card_and_page(): void
    {
        $negotiable = $this->tool(['en_name' => 'Leather saddle', 'price_negotiable' => true]);
        $this->tool(['en_name' => 'Hoof pick']);

        $this->get(route('tools-for-sale.index'))
            ->assertOk()
            ->assertSee('Leather saddle')
            ->assertSee('Hoof pick')
            ->assertSee('Negotiable');

        $this->get(route('tools-for-sale.show', $negotiable))
            ->assertOk()
            ->assertSee('Negotiable');

        $this->get(route('tools-for-sale.show', $negotiable).'?lang=ar')
            ->assertOk()
            ->assertSee('قابل للتفاوض');
    }

    public function test_a_fixed_price_tool_has_no_badge(): void
    {
        $tool = $this->tool();

        $this->get(route('tools-for-sale.index'))->assertOk()->assertDontSee('Negotiable');
        $this->get(route('tools-for-sale.show', $tool))->assertOk()->assertDontSee('Negotiable');
    }

    public function test_the_public_form_saves_the_negotiable_flag(): void
    {
        Storage::fake('public');

        $this->withSession(['filament_captcha_code' => $code = str_repeat('a', config('filament-captcha.length', 5))])
            ->post(route('tools-for-sale.store'), [
                'en_name' => 'Saddle',
                'ar_name' => 'سرج',
                'category' => 'saddlery_tack',
                'condition' => 'used',
                'city_id' => 1,
                'price' => '120',
                'price_negotiable' => '1',
                'cover_photo' => UploadedFile::fake()->image('saddle.jpg'),
                'contact_number' => '99999999',
                'captcha' => $code,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tools-for-sale.index'));

        $this->assertTrue(ToolSalePost::sole()->price_negotiable);
    }

    private function tool(array $attributes = []): ToolSalePost
    {
        return ToolSalePost::create(array_merge([
            'en_name' => 'Bridle',
            'ar_name' => 'لجام',
            'category' => 'saddlery_tack',
            'condition' => 'new',
            'country_id' => 1,
            'region_id' => 1,
            'city_id' => 1,
            'price' => 50,
            'contact_number' => '99999999',
            'status' => 'active',
        ], $attributes));
    }
}
