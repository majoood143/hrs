<?php

namespace Tests\Feature;

use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

/**
 * The source cannot search Arabic, so an Arabic query is matched in the source's Arabic lists and
 * never reaches its search: horses in the handicap list (the fake names "القوس (عُمان)" = Al Qous (OM),
 * id 1205, and "أعماق القوس (بريطانيا)" = Aamaaq (GB)), people in the Statistics lists (stats_*.json).
 */
class RacingArabicSearchTest extends TestCase
{
    use FakesRacingSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();

        $this->fakeRacingSource();
    }

    protected function tearDown(): void
    {
        $this->assertSame(0, $this->sentCount('page=Search'), 'the source search is never asked');

        parent::tearDown();
    }

    public function test_an_arabic_name_finds_the_horses_with_their_rating_and_last_run(): void
    {
        $this->get('/racing/search?type=1&lang=ar&q='.urlencode('القوس'))
            ->assertOk()
            ->assertSeeInOrder(['القوس (عُمان)', '70', 'أعماق القوس (بريطانيا)']) // names starting with the query first
            ->assertSee('/racing/horse/1205', false)
            ->assertSee('التقييم');
    }

    public function test_results_are_paginated(): void
    {
        config(['racing.per_page' => 1]);

        $this->get('/racing/search?type=1&lang=ar&q='.urlencode('القوس'))->assertOk()->assertSee('/racing/horse/1205', false)->assertDontSee('أعماق');
        $this->get('/racing/search?type=1&lang=ar&page=2&q='.urlencode('القوس'))->assertOk()->assertSee('أعماق القوس (بريطانيا)');
    }

    public function test_hamza_and_diacritics_do_not_matter(): void
    {
        $this->get('/racing/search?type=1&lang=ar&q='.urlencode('اعماق'))->assertOk()->assertSee('أعماق القوس (بريطانيا)');
        $this->get('/racing/search?type=1&lang=ar&q='.urlencode('القُوس'))->assertOk()->assertSee('/racing/horse/1205', false);
    }

    public function test_on_the_english_page_the_rows_are_in_english(): void
    {
        $this->get('/racing/search?type=1&lang=en&q='.urlencode('أعماق'))->assertOk()->assertSee('Aamaaq (GB)');
    }

    public function test_jockeys_owners_and_trainers_are_found_by_their_arabic_name(): void
    {
        // the most successful first: Anas (rank 2) before Mounes (rank 3)
        $this->get('/racing/search?type=3&lang=ar&q='.urlencode('السيابي'))
            ->assertOk()
            ->assertSeeInOrder(['أنس بن سالم بن عبدالله السيابي', '207', '1586', 'مؤنس بن سالم بن عبدالله السيابي'])
            ->assertSee('/racing/jockey/28', false)
            ->assertSee('المشاركات')
            ->assertDontSee('محمد بن سالم بن عبدالله السيابي'); // a trainer

        // tatweel in the source's name, hamza typed or not
        $this->get('/racing/search?type=2&lang=ar&q='.urlencode('الخيالة'))->assertOk()->assertSee('/racing/owner/298', false);
        $this->get('/racing/search?type=3&lang=ar&q='.urlencode('مونس'))->assertOk()->assertSee('/racing/jockey/88', false);

        $this->get('/racing/search?type=4&lang=ar&q='.urlencode('البادي'))->assertOk()->assertSee('/racing/trainer/118', false);
    }

    public function test_all_shows_every_group_with_matches(): void
    {
        $this->get('/racing/search?type=0&lang=ar&q='.urlencode('السيابي'))
            ->assertOk()
            ->assertSee('/racing/jockey/28', false)
            ->assertSee('/racing/trainer/106', false)
            ->assertDontSee('/racing/owner/', false);
    }

    public function test_on_the_english_page_people_are_named_in_english(): void
    {
        $this->get('/racing/search?type=4&lang=en&q='.urlencode('البادي'))->assertOk()->assertSee('Said bin Bati Al Badi');
    }

    public function test_the_api_searches_arabic_names_the_same_way(): void
    {
        $this->getJson('/api/v1/racing/search?type=horse&q='.urlencode('القوس'), ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.0.group', 'horse')
            ->assertJsonPath('data.0.total', 2)
            ->assertJsonPath('data.0.rows.0.id', 1205)
            ->assertJsonPath('data.0.rows.0.cells.0', 'القوس (عُمان)')
            ->assertJsonCount(3, 'data.0.headers')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/racing/search?q='.urlencode('السيابي'), ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.0.group', 'jockey')
            ->assertJsonPath('data.0.total', 2)
            ->assertJsonPath('data.0.rows.0.cells', ['أنس بن سالم بن عبدالله السيابي', '207', '1586'])
            ->assertJsonPath('data.1.group', 'trainer');

    }
}
