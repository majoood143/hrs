<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RacingSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The project's migrations are MySQL-only, so instead of RefreshDatabase we give the
        // site layout and the 404 fallback just what they read: empty site settings, menus and redirects.
        Cache::flush();
        Cache::forever('site_settings.all', collect());
        SiteSetting::resetMemo();
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->nullable();
            $table->string('to_path')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        config(['racing.base_url' => 'http://racing.test']);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/racing/{$name}.html"));
    }

    /** Fake the source: search fixtures by `type`, detail fixtures by entity id. */
    private function fakeSource(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $path = parse_url($request->url(), PHP_URL_PATH);

            if (($q['page'] ?? null) === 'Search') {
                return Http::response($this->fixture(match (true) {
                    ($q['QRECsearch'] ?? '') === 'zzzzqq' => 'search_empty',
                    (int) $q['type'] === 2 => 'search_owner',
                    default => 'search_horse',
                }));
            }

            return match (true) {
                str_contains($path, 'Horse_GenWin') && ($q['horseID'] ?? null) == 1205 => Http::response($this->fixture(($q['lang'] ?? 'en') === 'ar' ? 'horse_ar' : 'horse')),
                str_contains($path, 'Horse_GenWin') => Http::response($this->fixture('horse_missing')),
                str_contains($path, 'Owner_GenWin') => Http::response($this->fixture('owner')),
                str_contains($path, '/img/') => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg']),
                default => Http::response('nope', 404),
            };
        });
    }

    public function test_blank_query_shows_only_the_form(): void
    {
        Http::fake();

        $this->get('/racing/search')->assertOk()->assertSee('name="q"', false);

        Http::assertNothingSent();
    }

    public function test_short_query_is_rejected_without_calling_the_source(): void
    {
        Http::fake();

        $this->get('/racing/search?q=al&type=1')
            ->assertOk()
            ->assertSee('at least 3 characters');

        Http::assertNothingSent();
    }

    public function test_horse_results_link_to_the_profile_page(): void
    {
        $this->fakeSource();

        $this->get('/racing/search?q=al+qous&type=1')
            ->assertOk()
            ->assertSee('Al Qous (OM)')
            ->assertSee('Dahess')
            ->assertSee('/racing/horse/1205', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'QRECsearch=al%20qous') || str_contains($r->url(), 'QRECsearch=al+qous'));
    }

    public function test_search_forwards_type_and_locale_to_the_source(): void
    {
        $this->fakeSource();

        $this->get('/racing/search?q=kasbi&type=2&lang=ar')->assertOk();

        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['type'] === '2' && $q['lang'] === 'ar' && $q['QRECsearch'] === 'kasbi';
        });
    }

    public function test_no_results_message(): void
    {
        $this->fakeSource();

        $this->get('/racing/search?q=zzzzqq&type=1')->assertOk()->assertSee('No results found');
    }

    public function test_results_are_paginated(): void
    {
        config(['racing.per_page' => 2]);
        $this->fakeSource();

        $this->get('/racing/search?q=kasbi&type=2')->assertOk()->assertSee('Showing 1–2 of 6');
        $this->get('/racing/search?q=kasbi&type=2&page=3')->assertOk()->assertSee('Showing 5–6 of 6');
    }

    public function test_source_outage_shows_a_friendly_message_not_a_500(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->get('/racing/search?q=al+qous&type=1')
            ->assertOk()
            ->assertSee('temporarily unavailable');
    }

    public function test_upstream_http_error_is_also_handled(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->get('/racing/search?q=al+qous&type=1')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_results_are_cached(): void
    {
        $this->fakeSource();

        $this->get('/racing/search?q=al+qous&type=1')->assertOk();
        $this->get('/racing/search?q=AL+QOUS&type=1')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_horse_profile_renders_details_and_links(): void
    {
        $this->fakeSource();

        $this->get('/racing/horse/1205')
            ->assertOk()
            ->assertSee('Al Qous (OM)')
            ->assertSee('Dahess - Faurun')
            ->assertSee('NATIONAL MUSEUM 51ST NATIONAL DAY CUP')
            ->assertSee('/racing/owner/100', false)
            ->assertSee('/racing/jockey/128', false)
            ->assertSee('No upcoming entries');
    }

    public function test_arabic_profile_is_requested_and_rendered_rtl(): void
    {
        $this->fakeSource();

        $this->get('/racing/horse/1205?lang=ar')
            ->assertOk()
            ->assertSee('القوس (عمان)')
            ->assertSee('dir="rtl"', false);
    }

    public function test_unknown_horse_is_404(): void
    {
        $this->fakeSource();

        $this->get('/racing/horse/999999')->assertNotFound();
    }

    public function test_bad_entity_or_id_is_404(): void
    {
        Http::fake();

        $this->get('/racing/dog/1')->assertNotFound();
        $this->get('/racing/horse/abc')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_owner_profile_proxies_its_image_through_our_domain(): void
    {
        $this->fakeSource();

        $this->get('/racing/owner/100')
            ->assertOk()
            ->assertSee('/racing/image?p=', false)
            ->assertDontSee('185.64.25.43');
    }

    public function test_image_proxy_only_serves_img_paths(): void
    {
        $this->fakeSource();

        $this->get('/racing/image?p='.urlencode('/img/OwnerColours/basil masoud kasbi.jpg'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        foreach (['/pages/Horse_GenWin.cfm', '/img/../web.config', '//evil.test/img/a.jpg', '', '/img/x.php'] as $bad) {
            $this->get('/racing/image?p='.urlencode($bad))->assertNotFound();
        }
    }

    public function test_racing_is_not_swallowed_by_the_cms_catch_all_route(): void
    {
        $this->assertSame('racing.search', app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/racing/search'))->getName());
    }

    public function test_array_query_values_are_ignored_not_a_500(): void
    {
        Http::fake();

        $this->get('/racing/search?q[]=al&type[]=1')->assertOk();
        $this->get('/racing/image?p[]=/img/a.jpg')->assertNotFound();

        Http::assertNothingSent();
    }
}
