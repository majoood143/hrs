<?php

namespace Tests\Feature;

use App\Filament\Resources\SilkColorResource\Pages\ListSilkColors;
use App\Filament\Resources\SilkPatternResource\Pages\CreateSilkPattern;
use App\Filament\Resources\SilkPatternResource\Pages\EditSilkPattern;
use App\Filament\Resources\SilkPatternResource\Pages\ListSilkPatterns;
use App\Models\SilkColor;
use App\Models\SilkPattern;
use App\Models\SiteSetting;
use App\Support\Silks\SilksCatalog;
use App\Support\Silks\SilksDefaults;
use App\Support\Silks\SilksDesign;
use App\Support\Silks\SilksLink;
use App\Support\Silks\SilkSvgSanitizer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Mpdf\QrCode\Output\Svg;
use Mpdf\QrCode\QrCode;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/** The racing silks designer: the CMS block, its image download, and the admin patterns screen. */
class SilksDesignerTest extends TestCase
{
    use PreparesSiteLayout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();

        foreach ([
            '2025_10_03_170348_create_activity_log_table',
            '2025_10_03_170349_add_event_column_to_activity_log_table',
            '2025_10_03_170350_add_batch_uuid_column_to_activity_log_table',
            // creates the tables and inserts the default colours and patterns
            '2026_10_04_100000_create_silks_designer_tables',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    private function block(string $query = '', string $locale = 'en'): string
    {
        app()->setLocale($locale);
        $this->app->instance('request', Request::create('/design-your-silks'.$query));

        return View::make('cms.blocks.silks_designer', ['data' => []])->render();
    }

    // ── sanitizer ────────────────────────────────────────────────────────────

    public function test_every_default_pattern_passes_the_sanitizer_untouched(): void
    {
        foreach (SilksDefaults::patterns() as $pattern) {
            $result = SilkSvgSanitizer::sanitize($pattern['svg']);

            $this->assertNotNull($result['svg'], $pattern['area'].'/'.$pattern['key']);
            $this->assertSame([], $result['dropped'], $pattern['area'].'/'.$pattern['key']);
        }
    }

    public function test_the_sanitizer_keeps_only_shapes_geometry_and_the_pattern_colour(): void
    {
        $result = SilkSvgSanitizer::sanitize(
            '<rect x="1" width="5" onclick="alert(1)" fill="red"/><script>alert(1)</script>'
            .'<g transform="rotate(45)"><circle r="5" style="fill:red"/><a href="javascript:x"><rect/></a></g>'
            .'<image href="http://example.com/x.png"/><path d="M0 0 L10 10" stroke="currentColor"><animate/></path>'
        );

        $this->assertSame('<rect x="1" width="5"/><g transform="rotate(45)"><circle r="5"/></g><path d="M0 0 L10 10" stroke="currentColor"/>', $result['svg']);
        $this->assertEqualsCanonicalizing(['onclick', 'fill', '<script>', 'style', '<a>', '<image>', '<animate>'], $result['dropped']);
    }

    public function test_the_sanitizer_refuses_markup_that_is_not_well_formed_or_declares_entities(): void
    {
        $this->assertNull(SilkSvgSanitizer::sanitize('<rect x="1">')['svg']);
        $this->assertNull(SilkSvgSanitizer::sanitize('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><rect>&e;</rect>')['svg']);
        $this->assertNull(SilkSvgSanitizer::sanitize('just text')['svg']);
        $this->assertNull(SilkSvgSanitizer::sanitize(str_repeat('<rect/>', 6000))['svg']);

        // the model stores the cleaned markup whatever it is given
        $pattern = SilkPattern::create(['area' => 'cap', 'key' => 'dot', 'en_name' => 'Dot', 'ar_name' => 'نقطة', 'svg' => '<circle r="4" onload="x()"/>']);
        $this->assertSame('<circle r="4"/>', $pattern->fresh()->svg);
    }

    // ── the design ───────────────────────────────────────────────────────────

    public function test_a_design_falls_back_to_defaults_for_anything_unknown(): void
    {
        $design = SilksDesign::fromQuery([
            'body' => 'stars', 'body1' => 'red', 'body2' => 'no-such-colour',
            'sleeves' => 'no-such-pattern', 'sleeves1' => ['red'],
            'cap' => 'stripes', // a body pattern, not a cap one
        ], SilksCatalog::load());

        $this->assertSame([
            'body' => 'stars', 'body1' => 'red', 'body2' => 'white',
            'sleeves' => 'plain', 'sleeves1' => 'white', 'sleeves2' => 'royal-blue',
            'cap' => 'star', 'cap1' => 'royal-blue', 'cap2' => 'white',
        ], $design->query());

        // a switched-off pattern is no longer offered: an old link to it draws the default
        SilkPattern::query()->where('area', 'body')->where('key', 'stars')->update(['is_active' => false]);
        $this->assertSame('hoops', SilksDesign::fromQuery(['body' => 'stars'], SilksCatalog::load())->query()['body']);
    }

    public function test_the_description_reads_like_registered_colours_in_both_languages(): void
    {
        $query = ['body' => 'chevrons', 'body1' => 'red', 'body2' => 'white', 'sleeves' => 'plain', 'sleeves1' => 'dark-blue', 'cap' => 'quartered', 'cap1' => 'red', 'cap2' => 'white'];

        app()->setLocale('en');
        $this->assertSame('Red, white chevrons, dark blue sleeves, red cap, white quarters', SilksDesign::fromQuery($query, SilksCatalog::load())->describe());

        app()->setLocale('ar');
        $this->assertSame('لون الجسم أحمر مع شيفرونات بلون أبيض، لون الأكمام أزرق داكن، لون القبعة أحمر مع أرباع بلون أبيض', SilksDesign::fromQuery($query, SilksCatalog::load())->describe());
    }

    public function test_switching_every_colour_off_still_leaves_a_working_designer(): void
    {
        SilkColor::query()->update(['is_active' => false]);

        $catalog = SilksCatalog::load();
        $this->assertCount(count(SilksDefaults::colors()), $catalog->colors);
        $this->assertSame('#1F4FBF', SilksDesign::fromQuery([], $catalog)->paint()['body']['base']);
    }

    // ── the block ────────────────────────────────────────────────────────────

    public function test_the_block_draws_the_design_in_the_query_string_without_javascript(): void
    {
        $html = $this->block('?body=sash&body1=dark-green&body2=yellow&cap=plain&cap1=red');

        $this->assertStringContainsString('data-silks-designer', $html);
        $this->assertMatchesRegularExpression('#name="body" value="sash"\s+class="sr-only"\s+checked#', $html);
        $this->assertMatchesRegularExpression('#name="body1" value="dark-green" class="peer sr-only"\s+checked#', $html);
        $this->assertStringContainsString('Dark green, yellow sash, white sleeves, red cap', $html);
        // the drawing itself is painted on the server
        $this->assertStringContainsString('fill="#0F4D2C" data-silks-base="body"', $html);
        $this->assertStringContainsString('color="#F7D417" data-silks-pattern="body" data-silks-key="sash"', $html);
        // the cap is plain: its pattern colour row starts hidden
        $this->assertMatchesRegularExpression('#data-silks-accent-row="cap"\s+hidden#', $html);
        $this->assertDoesNotMatchRegularExpression('#data-silks-accent-row="body"\s+hidden#', $html);
        // "Download SVG" is a plain link carrying the design; the form can be submitted
        $this->assertStringContainsString(e(route('silks.image', ['body' => 'sash', 'body1' => 'dark-green', 'body2' => 'yellow', 'sleeves' => 'plain', 'sleeves1' => 'white', 'sleeves2' => 'royal-blue', 'cap' => 'plain', 'cap1' => 'red', 'cap2' => 'white', 'page' => url('/design-your-silks'), 'download' => 1])), $html);
        $this->assertStringContainsString('type="submit"', $html);
        // every active body pattern has a button, plus plain
        $this->assertSame(SilkPattern::query()->where('area', 'body')->count() + 1, substr_count($html, 'name="body" value='));
    }

    public function test_the_block_hides_switched_off_colours_and_patterns(): void
    {
        SilkColor::query()->where('key', 'mauve')->update(['is_active' => false]);
        SilkPattern::query()->where('area', 'body')->where('key', 'horseshoe')->update(['is_active' => false]);

        $html = $this->block();

        $this->assertStringNotContainsString('value="mauve"', $html);
        $this->assertStringNotContainsString('name="body" value="horseshoe"', $html);
        $this->assertStringContainsString('value="purple"', $html);
    }

    public function test_the_block_is_translated_and_keeps_the_drawing_left_to_right(): void
    {
        $html = $this->block('', 'ar');

        $this->assertStringContainsString('صمّم ألوان السباق الخاصة بك', $html);
        $this->assertStringContainsString('لون الجسم أزرق ملكي مع أطواق بلون أبيض', $html);
        $this->assertMatchesRegularExpression('#<svg[^>]+dir="ltr"[^>]+data-silks-figure#', $html);
    }

    // ── the image ────────────────────────────────────────────────────────────

    public function test_the_image_is_a_standalone_svg_with_its_description_underneath(): void
    {
        $response = $this->get('/silks/image?body=diamonds&body1=maroon&body2=white&download=1');

        $response->assertOk();
        $this->assertStringStartsWith('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('attachment; filename="racing-silks.svg"', $response->headers->get('Content-Disposition'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'the file must be well-formed XML, or browsers will not open it');
        $this->assertSame('http://www.w3.org/2000/svg', $xml->getNamespaces()['']);
        $this->assertSame('400', (string) $xml['width']);

        $text = implode(' ', array_map('strval', $xml->xpath('//*[local-name()="text"]')));
        $this->assertStringContainsString('Maroon, white diamonds, white sleeves,', $text);
        $this->assertStringContainsString('Designed on', $text);
    }

    public function test_the_image_is_shown_inline_unless_downloaded_and_follows_the_language(): void
    {
        $response = $this->get('/silks/image?lang=ar');

        $response->assertOk();
        $this->assertNull($response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('direction="rtl"', $response->getContent());
        $this->assertStringContainsString('لون الجسم أزرق ملكي', $response->getContent());
    }

    /** A PNG logo uploaded in General Settings (RacingPdf::siteLogo() reads it from the public disk). */
    private function withLogo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', $this->png(360, 100));

        Cache::forever('site_settings.all', collect(['site_logo' => (object) ['key' => 'site_logo', 'type' => 'text', 'value' => 'branding/logo.png']]));
        SiteSetting::resetMemo();
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 60, 120));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_the_downloaded_image_carries_the_site_logo_but_the_bare_one_does_not(): void
    {
        $this->withLogo();

        $full = $this->get('/silks/image')->assertOk();
        $xml = simplexml_load_string($full->getContent());
        $logo = $xml->xpath('//*[local-name()="image"]');
        $this->assertCount(1, $logo);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $logo[0]['href']);
        // the logo is embedded, so the file's own CSP must let a data: image show
        $this->assertStringContainsString('img-src data:', $full->headers->get('Content-Security-Policy'));
        // the drawing moves down below the logo
        $this->assertGreaterThan(400 + 60, (int) $xml['height']);

        $bare = simplexml_load_string($this->get('/silks/image?bare=1')->assertOk()->getContent());
        $this->assertSame([], $bare->xpath('//*[local-name()="image"]'));
        $this->assertSame([], $bare->xpath('//*[local-name()="text"]'));
        $this->assertSame('400', (string) $bare['height']);
    }

    public function test_the_pdf_is_built_around_the_drawing_the_browser_sends(): void
    {
        $this->withLogo();

        $response = $this->post('/silks/pdf', [
            'body' => 'diamonds', 'body1' => 'dark-green', 'body2' => 'yellow',
            'image' => 'data:image/png;base64,'.base64_encode($this->png(1200, 1200)),
            'page' => 'http://localhost/design-your-silks?body=diamonds',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="racing-silks.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_pdf_page_spells_out_every_part_and_links_back_only_to_this_site(): void
    {
        $design = SilksDesign::fromQuery(['body' => 'diamonds', 'body1' => 'dark-green', 'body2' => 'yellow', 'sleeves' => 'plain', 'sleeves1' => 'white'], SilksCatalog::load());

        $this->assertSame([
            'pattern' => 'Plain',
            'base' => ['name' => 'White', 'hex' => '#FFFFFF'],
            'accent' => null,
        ], $design->details()['sleeves']);
        $this->assertSame(['name' => 'Yellow', 'hex' => '#F7D417'], $design->details()['body']['accent']);

        // the link (and QR code) reopens the design on the page it was made on, in the PDF's language
        $this->app->instance('request', Request::create('http://localhost/silks/pdf', 'POST'));
        app()->setLocale('ar');
        $this->assertSame(
            'http://localhost/design-your-silks?lang=ar&silks=diamonds.dark-green.yellow~plain.white.royal-blue~star.royal-blue.white',
            SilksLink::for('http://localhost/design-your-silks?utm=x&body=stars', $design),
        );
        // only a page on this site: the address comes from the visitor
        $this->assertNull(SilksLink::for('https://evil.example/design-your-silks', $design));
        $this->assertNull(SilksLink::for('javascript:alert(1)', $design));
        $this->assertNull(SilksLink::for(['http://localhost/'], $design));
    }

    public function test_the_short_form_round_trips_and_spelled_out_parameters_win(): void
    {
        $catalog = SilksCatalog::load();
        $design = SilksDesign::fromQuery(['body' => 'sash', 'body1' => 'maroon', 'body2' => 'beige', 'sleeves' => 'armlet', 'cap' => 'quartered'], $catalog);

        $this->assertSame($design->query(), SilksDesign::fromQuery(['silks' => $design->compact()], $catalog)->query());
        $this->assertSame('stars', SilksDesign::fromQuery(['silks' => $design->compact(), 'body' => 'stars'], $catalog)->query()['body']);

        // a mangled short form falls back part by part, like any unknown key
        $this->assertSame('hoops.royal-blue.white~plain.white.royal-blue~star.red.white', SilksDesign::fromQuery(['silks' => 'nope~~star.red'], $catalog)->compact());
    }

    public function test_the_downloaded_image_has_a_qr_code_for_its_own_page_only(): void
    {
        $page = url('/design-your-silks');
        $withPage = simplexml_load_string($this->get('/silks/image?page='.urlencode($page))->assertOk()->getContent());
        $this->assertNotEmpty($withPage->xpath('//*[local-name()="g"][@shape-rendering="crispEdges"]/*[local-name()="rect"]'));
        $this->assertStringContainsString('Scan to open this design', implode(' ', array_map('strval', $withPage->xpath('//*[local-name()="text"]'))));

        foreach (['', '?page='.urlencode('https://evil.example/x'), '?bare=1&page='.urlencode($page)] as $query) {
            $svg = simplexml_load_string($this->get('/silks/image'.$query)->assertOk()->getContent());
            $this->assertSame([], $svg->xpath('//*[local-name()="g"][@shape-rendering="crispEdges"]'), $query);
        }
    }

    public function test_the_qr_code_matches_the_library_reference_module_for_module(): void
    {
        $url = 'http://localhost/design-your-silks?lang=en&silks=hoops.royal-blue.white~plain.white.royal-blue~star.royal-blue.white';
        $qr = new QrCode($url, 'M');
        $qr->disableBorder();
        $modules = $qr->getQrSize() - 8;

        $grid = function (string $svg, string $pattern) use ($modules) {
            $cells = array_fill(0, $modules * $modules, 0);
            preg_match_all($pattern, $svg, $rects, PREG_SET_ORDER);
            foreach ($rects as [, $x, $y, $width]) {
                for ($col = (int) round($x); $col < (int) round($x + $width); $col++) {
                    $cells[(int) round($y) * $modules + $col] = 1;
                }
            }

            return $cells;
        };

        $reference = $grid((new Svg)->output($qr, $modules), '/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="[\d.]+" fill="black"/');
        $ours = $grid(SilksLink::qrRects($url, $modules), '/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)"/');

        $this->assertGreaterThan(100, array_sum($ours));
        $this->assertSame($reference, $ours);
    }

    public function test_the_pdf_refuses_anything_but_a_png_of_a_sane_size(): void
    {
        $jpeg = (function () {
            $image = imagecreatetruecolor(400, 400);
            ob_start();
            imagejpeg($image);

            return (string) ob_get_clean();
        })();

        foreach ([
            'data:image/png;base64,'.base64_encode('not an image'),
            'data:image/png;base64,'.base64_encode($jpeg), // a JPEG claiming to be a PNG
            'data:image/png;base64,'.base64_encode($this->png(50, 50)), // too small to be our drawing
            'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'http://example.com/silks.png',
        ] as $image) {
            $this->post('/silks/pdf', ['image' => $image])->assertStatus(422);
        }

        $this->postJson('/silks/pdf', [])->assertJsonValidationErrors('image');
    }

    // ── admin ────────────────────────────────────────────────────────────────

    private function actingAsAdmin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_an_admin_can_add_a_pattern_and_unsafe_or_reserved_input_is_refused(): void
    {
        $this->actingAsAdmin();

        $form = ['area' => 'cap', 'key' => 'ring', 'en_name' => 'Ring', 'ar_name' => 'حلقة', 'sort' => 99, 'is_active' => true];

        Livewire::test(CreateSilkPattern::class)
            ->fillForm($form + ['svg' => '<circle cx="70" cy="40" r="20" onclick="steal()"/>'])
            ->call('create')
            ->assertHasFormErrors(['svg']);

        Livewire::test(CreateSilkPattern::class)
            ->fillForm(['key' => 'plain'] + $form + ['svg' => '<circle r="4"/>'])
            ->call('create')
            ->assertHasFormErrors(['key']);

        // the key is unique per part: a body "star" exists already, a cap "star" too
        Livewire::test(CreateSilkPattern::class)
            ->fillForm(['key' => 'star'] + $form + ['svg' => '<circle r="4"/>'])
            ->call('create')
            ->assertHasFormErrors(['key']);

        Livewire::test(CreateSilkPattern::class)
            ->fillForm($form + ['svg' => '<circle cx="70" cy="40" r="20" fill="none" stroke="currentColor" stroke-width="6"/>'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('<circle cx="70" cy="40" r="20" fill="none" stroke="currentColor" stroke-width="6"/>', SilkPattern::query()->where('area', 'cap')->where('key', 'ring')->value('svg'));
        $this->assertTrue(SilksCatalog::load()->hasPattern('cap', 'ring'));
    }

    public function test_missing_default_patterns_can_be_added_back(): void
    {
        $this->actingAsAdmin();

        SilkPattern::query()->where('key', 'stars')->delete();
        SilkPattern::query()->where('key', 'hoops')->update(['en_name' => 'My hoops']);

        Livewire::test(ListSilkPatterns::class)
            ->callAction('restoreDefaults')
            ->assertNotified();

        $this->assertSame(3, SilkPattern::query()->where('key', 'stars')->count());
        $this->assertSame(count(SilksDefaults::patterns()), SilkPattern::query()->count());
        // an edited default is left alone
        $this->assertSame('My hoops', SilkPattern::query()->where('area', 'body')->where('key', 'hoops')->value('en_name'));
    }

    public function test_the_admin_screens_render_with_previews(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListSilkColors::class)
            ->assertOk()
            ->assertCanSeeTableRecords(SilkColor::query()->orderBy('sort')->limit(5)->get());

        $pattern = SilkPattern::query()->where('area', 'sleeves')->where('key', 'armlet')->firstOrFail();

        // the edit form draws the pattern on the silks: the sleeves carry it, the other parts are plain
        Livewire::test(EditSilkPattern::class, ['record' => $pattern->getRouteKey()])
            ->assertOk()
            ->assertSeeHtml('data-silks-pattern="sleeves" data-silks-key="__preview"')
            ->assertSeeHtml('data-silks-pattern="body" data-silks-key="plain"')
            ->fillForm(['svg' => '<rect x="0" y="10" width="60" height="5"/>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('<rect x="0" y="10" width="60" height="5"/>', $pattern->fresh()->svg);
    }
}
