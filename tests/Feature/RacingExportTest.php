<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Racing\RacingPdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

class RacingExportTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();
        $this->fakeRacingSource();
    }

    private function assertPdf(TestResponse $response, ?string $filename = null): void
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(5000, strlen($response->getContent()));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        if ($filename !== null) {
            $this->assertStringContainsString("filename={$filename}", $response->headers->get('Content-Disposition'));
        }
    }

    // ---- the toolbar on the pages -------------------------------------------------------------

    public function test_a_profile_has_print_pdf_and_share(): void
    {
        $html = $this->get('/racing/horse/1205')->assertOk()->getContent();

        $this->assertStringContainsString('data-racing-tools', $html);
        $this->assertStringContainsString('href="'.url('/racing/horse/1205/pdf').'"', $html);

        // Print is rendered hidden and only revealed by the script that makes it work
        $this->assertMatchesRegularExpression('#<button type="button" data-print hidden#', $html);

        // the existing share icons, pointing at this page in this language
        $page = urlencode(url('/racing/horse/1205').'?lang=en');
        $this->assertStringContainsString('https://www.facebook.com/sharer/sharer.php?u='.$page, $html);
        $this->assertStringContainsString('https://t.me/share/url?url='.$page, $html);
        $this->assertStringContainsString('https://wa.me/?text='.urlencode('Al Qous (OM) — Horse '.url('/racing/horse/1205').'?lang=en'), $html);
        $this->assertStringContainsString('data-copy-link="'.url('/racing/horse/1205').'?lang=en"', $html);

        // the listing-style card and heading are not used here
        $this->assertStringNotContainsString('Share this listing', $html);
    }

    public function test_a_race_page_shares_and_exports_the_race_it_shows(): void
    {
        // /racing/results shows the latest meeting: the links must name the race explicitly, not "latest"
        $html = $this->get('/racing/results')->assertOk()->getContent();

        preg_match('#/racing/results/(\d+)/pdf#', $html, $m);
        $this->assertNotEmpty($m, 'a PDF link for the race on screen');
        $this->assertStringContainsString('data-copy-link="'.url("/racing/results/{$m[1]}").'?lang=en"', $html);
    }

    public function test_the_share_link_keeps_the_language(): void
    {
        $html = $this->get('/racing/horse/1205?lang=ar')->assertOk()->getContent();

        $this->assertStringContainsString('data-copy-link="'.url('/racing/horse/1205').'?lang=ar"', $html);
        $this->assertStringContainsString('مشاركة', $html);
        $this->assertStringContainsString('تنزيل بصيغة PDF', $html);
    }

    public function test_a_race_with_nothing_published_can_be_shared_but_not_exported(): void
    {
        $html = $this->get('/racing/entries')->assertOk()->assertSee('Entries are not available for this race.')->getContent();

        $this->assertStringContainsString('data-racing-tools', $html);
        $this->assertStringContainsString('data-copy-link=', $html);
        $this->assertStringNotContainsString('/pdf"', $html);
    }

    public function test_a_shared_page_has_a_description_for_the_preview(): void
    {
        $profile = $this->get('/racing/horse/1205')->getContent();
        $this->assertMatchesRegularExpression('#<meta property="og:description" content="Horse · [^"]+"#', $profile);

        $race = $this->get('/racing/results/9178')->getContent();
        $this->assertMatchesRegularExpression('#<meta property="og:description" content="[^"]*Al Rahba[^"]*"#', $race);
    }

    public function test_printing_shows_every_tab_and_hides_the_site_chrome(): void
    {
        $html = $this->get('/racing/horse/1205')->getContent();

        $this->assertMatchesRegularExpression('#<header class="[^"]*print:hidden#', $html);
        $this->assertMatchesRegularExpression('#<footer class="[^"]*print:hidden#', $html);
        $this->assertMatchesRegularExpression('#<nav[^>]*aria-label="Racing Database"[^>]*print:hidden#', $html);
        $this->assertStringContainsString('data-racing-print', $html);

        // one print-only heading for each tab panel
        $panels = substr_count($html, 'role="tabpanel"');
        $this->assertGreaterThan(1, $panels);
        $this->assertSame($panels, substr_count($html, 'hidden font-display text-lg font-semibold text-warm-900 print:block'));
    }

    public function test_the_shared_share_component_is_unchanged_for_listings(): void
    {
        $html = Blade::render('<x-share-buttons url="https://x.test/a" title="A" />');

        $this->assertStringContainsString('card-warm', $html);
        $this->assertStringContainsString('Share this listing', $html);
        $this->assertStringContainsString('wa.me', $html);

        $custom = Blade::render('<x-share-buttons url="https://x.test/a" title="A" heading="Pass it on" />');
        $this->assertStringContainsString('Pass it on', $custom);
        $this->assertStringNotContainsString('Share this listing', $custom);
    }

    // ---- PDF ----------------------------------------------------------------------------------

    public function test_a_horse_profile_downloads_as_a_pdf(): void
    {
        $this->assertPdf($this->get('/racing/horse/1205/pdf'), 'al-qous-om.pdf');
    }

    public function test_the_arabic_profile_pdf_keeps_its_arabic_file_name(): void
    {
        $response = $this->get('/racing/horse/1205/pdf?lang=ar');

        $this->assertPdf($response);
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('القوس (عمان)').'.pdf', $response->headers->get('Content-Disposition'));
        // and an ASCII name for clients that cannot read it
        $this->assertMatchesRegularExpression('#filename=[A-Za-z0-9._-]+\.pdf#', $response->headers->get('Content-Disposition'));
    }

    public function test_owner_jockey_and_trainer_profiles_export_too(): void
    {
        foreach (['owner' => 100, 'jockey' => 128, 'trainer' => 7] as $entity => $id) {
            $this->assertPdf($this->get("/racing/{$entity}/{$id}/pdf"));
        }
    }

    public function test_every_race_page_exports_in_both_languages(): void
    {
        foreach (['results', 'entries', 'card', 'form-guide'] as $page) {
            $this->assertPdf($this->get("/racing/{$page}/9178/pdf"));
            $this->assertPdf($this->get("/racing/{$page}/9178/pdf?lang=ar"));
        }
    }

    public function test_the_pdfs_use_cairo_in_both_languages_and_nothing_else(): void
    {
        foreach (['/racing/horse/1205/pdf', '/racing/horse/1205/pdf?lang=ar', '/racing/card/9178/pdf', '/racing/card/9178/pdf?lang=ar'] as $url) {
            $pdf = $this->get($url)->assertOk()->getContent();

            preg_match_all('#/BaseFont\s*/[A-Z]+\+([A-Za-z-]+)#', $pdf, $m);
            $fonts = array_values(array_unique($m[1]));
            sort($fonts);

            $this->assertSame(['Cairo-Bold', 'Cairo-Regular'], $fonts, "fonts in {$url}");
        }

        $this->assertFileExists(resource_path('fonts/cairo/Cairo-Regular.ttf'));
        $this->assertFileExists(resource_path('fonts/cairo/Cairo-Bold.ttf'));
        $this->assertFileExists(resource_path('fonts/cairo/OFL.txt')); // the licence travels with the font
    }

    public function test_every_pdf_carries_a_qr_code_to_its_page_in_its_language(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $html = view('pdf.racing.layout', [
                'locale' => $locale,
                'rtl' => $locale === 'ar',
                'siteName' => 'Muhra',
                'logo' => null,
                'title' => 'T',
                'url' => 'https://site.test/racing/horse/1205',
                'qrUrl' => "https://site.test/racing/horse/1205?lang={$locale}",
            ])->render();

            $this->assertStringContainsString('<barcode code="https://site.test/racing/horse/1205?lang='.$locale.'" type="QR"', $html);
        }

        // and it is a real, generated code: the PDF is built without the QR package missing
        $this->assertPdf($this->get('/racing/results/9178/pdf'));
    }

    public function test_the_pdf_views_are_black_white_and_gray_only(): void
    {
        foreach (glob(resource_path('views/pdf/racing/*.blade.php')) as $file) {
            preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', file_get_contents($file), $m);

            foreach ($m[1] as $hex) {
                $hex = strlen($hex) === 3 ? preg_replace('/./', '$0$0', $hex) : $hex;

                $this->assertSame(substr($hex, 0, 2), substr($hex, 2, 2), basename($file)." has a coloured value #{$hex}");
                $this->assertSame(substr($hex, 2, 2), substr($hex, 4, 2), basename($file)." has a coloured value #{$hex}");
            }
        }
    }

    // ---- the logo ------------------------------------------------------------------------------

    /** Point the "site_logo" setting at a file on the public disk (the setting is cached, so it is set there). */
    private function useLogo(?string $path, ?string $bytes = null): void
    {
        Storage::fake('public');

        if ($path !== null && $bytes !== null) {
            Storage::disk('public')->put($path, $bytes);
        }

        Cache::forever('site_settings.all', collect($path === null ? [] : [
            'site_logo' => new SiteSetting(['key' => 'site_logo', 'type' => 'file', 'value' => $path]),
        ]));
        SiteSetting::resetMemo();
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 40, 40));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Image objects in a PDF: the QR code is drawn as vectors, so only a logo adds any (a transparent one adds its mask too). */
    private function imageCount(string $pdf): int
    {
        return preg_match_all('#/Subtype\s*/Image#', $pdf);
    }

    public function test_the_uploaded_logo_is_in_the_pdf(): void
    {
        $this->useLogo('branding/logo.png', $this->png(900, 300));

        foreach (['/racing/horse/1205/pdf', '/racing/horse/1205/pdf?lang=ar', '/racing/results/9178/pdf'] as $url) {
            $this->assertGreaterThan(0, $this->imageCount($this->get($url)->assertOk()->getContent()), $url);
        }
    }

    public function test_without_a_logo_the_site_name_is_used_instead(): void
    {
        $this->useLogo(null);

        $this->assertSame(0, $this->imageCount($this->get('/racing/horse/1205/pdf')->assertOk()->getContent()));

        $html = view('pdf.racing.layout', ['locale' => 'en', 'rtl' => false, 'siteName' => 'Muhra', 'logo' => null, 'title' => 'T', 'url' => 'u', 'qrUrl' => 'u'])->render();
        $this->assertStringContainsString('<div class="site">Muhra</div>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_a_logo_that_is_missing_or_broken_falls_back_to_the_site_name(): void
    {
        // the setting names a file that is not there
        $this->useLogo('branding/gone.png');
        $this->assertNull(app(RacingPdf::class)->siteLogo());
        $this->assertPdf($this->get('/racing/horse/1205/pdf'));

        // a file that is not an image, and a pdf someone uploaded by mistake
        foreach (['branding/notes.png' => 'not an image', 'branding/doc.pdf' => "%PDF-1.4\n%%EOF"] as $path => $bytes) {
            $this->useLogo($path, $bytes);

            $this->assertNull(app(RacingPdf::class)->siteLogo(), $path);
            $this->assertSame(0, $this->imageCount($this->get('/racing/horse/1205/pdf')->assertOk()->getContent()), $path);
        }
    }

    public function test_a_huge_logo_is_shrunk_and_fitted_to_the_header(): void
    {
        $this->useLogo('branding/big.png', $this->png(2400, 600));

        $logo = app(RacingPdf::class)->siteLogo();

        [$width, $height] = getimagesizefromstring(base64_decode(substr($logo['src'], strlen('data:image/png;base64,'))));
        $this->assertLessThanOrEqual(600, $width);
        $this->assertSame(4.0, round($width / $height, 1));
        $this->assertLessThan(strlen($this->png(2400, 600)), strlen(base64_decode(substr($logo['src'], 22))));

        // 4:1 in a 14 mm x 50 mm box
        $this->assertEqualsWithDelta(12.5, $logo['height'], 0.01);
        $this->assertEqualsWithDelta(50.0, $logo['width'], 0.01);

        // a tall logo is limited by the height instead
        $this->useLogo('branding/tall.png', $this->png(300, 600));
        $tall = app(RacingPdf::class)->siteLogo();
        $this->assertEqualsWithDelta(14.0, $tall['height'], 0.01);
        $this->assertEqualsWithDelta(7.0, $tall['width'], 0.01);
    }

    public function test_an_enormous_logo_is_left_out_rather_than_exhausting_memory(): void
    {
        // a valid PNG header claiming 20000 x 20000 px (1.6 GB decoded); nothing behind it is ever read
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));

        $this->useLogo('branding/huge.png', $header);

        $this->assertNull(app(RacingPdf::class)->siteLogo());
        $this->assertPdf($this->get('/racing/horse/1205/pdf'));
    }

    public function test_jpg_and_webp_logos_are_converted_and_used(): void
    {
        foreach (['jpg' => fn ($i) => imagejpeg($i), 'webp' => fn ($i) => imagewebp($i), 'gif' => fn ($i) => imagegif($i)] as $ext => $write) {
            $image = imagecreatetruecolor(400, 200);
            ob_start();
            $write($image);
            $this->useLogo("branding/logo.{$ext}", (string) ob_get_clean());

            $logo = app(RacingPdf::class)->siteLogo();

            $this->assertNotNull($logo, $ext);
            $this->assertStringStartsWith('data:image/png;base64,', $logo['src'], $ext);
            $this->assertEqualsWithDelta(2.0, $logo['width'] / $logo['height'], 0.01, $ext);
            $this->assertGreaterThan(0, $this->imageCount($this->get('/racing/horse/1205/pdf')->assertOk()->getContent()), $ext);
        }
    }

    public function test_an_svg_logo_is_used_with_its_own_proportions(): void
    {
        $this->useLogo('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"><rect width="300" height="100" fill="#222"/></svg>');

        $logo = app(RacingPdf::class)->siteLogo();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $logo['src']);
        $this->assertEqualsWithDelta(3.0, $logo['width'] / $logo['height'], 0.01);
        $this->assertPdf($this->get('/racing/horse/1205/pdf'));

        // an svg with no size at all cannot be laid out: name instead
        $this->useLogo('branding/odd.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        $this->assertNull(app(RacingPdf::class)->siteLogo());
    }

    public function test_unknown_things_are_404_and_never_reach_the_slow_source_pages(): void
    {
        $this->get('/racing/horse/999/pdf')->assertNotFound();
        $this->get('/racing/stable/1/pdf')->assertNotFound();
        $this->get('/racing/horse/abc/pdf')->assertNotFound();
        $this->get('/racing/results/99999999/pdf')->assertNotFound();
        $this->get('/racing/results/abc/pdf')->assertNotFound();

        // the unknown race id was vetted with the cheap call, so the heavy shell pages were never requested
        $this->assertSame(0, $this->sentCount('page=RaceResults'));
    }

    public function test_a_race_with_nothing_published_has_no_pdf(): void
    {
        // 10005 is upcoming: the source answers its entries with an "unavailable" page
        $this->get('/racing/entries/10005/pdf')->assertNotFound();
    }

    public function test_when_the_source_is_down_the_pdf_link_lands_on_the_page_that_says_so(): void
    {
        $this->replaceRacingFake(fn () => throw new ConnectionException('timeout'));

        $this->get('/racing/horse/1205/pdf')->assertRedirect('/racing/horse/1205');
        $this->get('/racing/results/9178/pdf')->assertRedirect('/racing/results/9178');

        $this->get('/racing/horse/1205')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_the_pdf_uses_the_cached_source_data(): void
    {
        $this->get('/racing/horse/1205')->assertOk();
        $this->get('/racing/horse/1205/pdf')->assertOk();
        $this->get('/racing/horse/1205/pdf')->assertOk();

        $this->assertSame(1, $this->sentCount('Horse_GenWin'));
    }

    public function test_repeat_downloads_of_the_same_pdf_skip_the_expensive_render(): void
    {
        // mPDF (Arabic shaping, the Cairo font) is the expensive step, distinct from the
        // underlying data cache the test above covers: render() must run only once even though
        // the PDF is downloaded (and, for the race page, requested in a second language) several times.
        $this->partialMock(RacingPdf::class, fn ($mock) => $mock->shouldReceive('render')->once()->passthru());

        $first = $this->get('/racing/horse/1205/pdf')->assertOk()->getContent();
        $second = $this->get('/racing/horse/1205/pdf')->assertOk()->getContent();

        $this->assertSame($first, $second);
    }

    public function test_a_different_language_renders_its_own_pdf_not_the_other_languages_cached_one(): void
    {
        $this->partialMock(RacingPdf::class, fn ($mock) => $mock->shouldReceive('render')->twice()->passthru());

        $en = $this->get('/racing/horse/1205/pdf')->assertOk()->getContent();
        $ar = $this->get('/racing/horse/1205/pdf?lang=ar')->assertOk()->getContent();

        $this->assertNotSame($en, $ar);
    }
}
