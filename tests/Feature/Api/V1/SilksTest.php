<?php

namespace Tests\Feature\Api\V1;

use App\Support\Silks\SilksDefaults;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

class SilksTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();

        // creates the tables and inserts the default colours and patterns
        (require database_path('migrations/2026_10_04_100000_create_silks_designer_tables.php'))->up();
    }

    public function test_it_sends_the_active_colours_and_patterns_in_the_requested_language(): void
    {
        DB::table('silk_colors')->where('key', 'grey')->update(['is_active' => false]);
        DB::table('silk_patterns')->where('area', 'body')->where('key', 'hoops')->update(['is_active' => false]);

        $bodyPatterns = collect(SilksDefaults::patterns())->where('area', 'body')->where('key', '!=', 'hoops');

        $this->getJson('/api/v1/silks')
            ->assertOk()
            ->assertJsonCount(count(SilksDefaults::colors()) - 1, 'data.colors')
            ->assertJsonPath('data.colors.0', ['key' => 'white', 'name' => 'White', 'hex' => '#FFFFFF'])
            ->assertJsonMissingPath('data.colors.17')
            ->assertJsonCount($bodyPatterns->count(), 'data.patterns.body')
            ->assertJsonPath('data.patterns.body.0.key', $bodyPatterns->first()['key'])
            ->assertJsonPath('data.patterns.body.0.svg', $bodyPatterns->first()['svg'])
            ->assertJsonPath('data.plain', 'Plain')
            ->assertJsonPath('data.describe.body_pattern', ':base, :colour :pattern')
            // hoops is off, so the default body falls back to plain, as on the website
            ->assertJsonPath('data.default.body', ['pattern' => 'plain', 'base' => 'royal-blue', 'accent' => 'white'])
            ->assertJsonPath('data.default.cap.pattern', 'star')
            ->assertJsonPath('data.page_url', null);

        $this->getJson('/api/v1/silks', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.colors.0.name', 'أبيض')
            ->assertJsonPath('data.plain', __('silks.plain', [], 'ar'))
            ->assertJsonPath('data.describe.separator', '، ');
    }

    public function test_the_page_url_is_the_first_published_page_with_the_designer_block(): void
    {
        $blocks = fn (string $type) => json_encode([['type' => 'heading', 'data' => []], ['type' => $type, 'data' => []]]);

        $this->row('cms_pages', ['title' => '{"en":"Draft"}', 'slug' => 'draft-silks', 'content' => $blocks('silks_designer'), 'status' => 'draft']);
        $this->row('cms_pages', ['title' => '{"en":"Mentions"}', 'slug' => 'mentions', 'content' => json_encode([['type' => 'text', 'data' => ['body' => 'silks_designer']]])]);
        $this->row('cms_pages', ['title' => '{"en":"Silks"}', 'slug' => 'design-your-silks', 'content' => $blocks('silks_designer')]);

        $this->getJson('/api/v1/silks')
            ->assertOk()
            ->assertJsonPath('data.page_url', url('/design-your-silks'));
    }
}
