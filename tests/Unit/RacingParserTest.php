<?php

namespace Tests\Unit;

use App\Services\Racing\RacingParser;
use PHPUnit\Framework\TestCase;

class RacingParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . "/../Fixtures/racing/{$name}.html");
    }

    public function test_it_parses_horse_search_rows_with_ids(): void
    {
        $groups = (new RacingParser())->parseSearch($this->fixture('search_horse'));

        $this->assertSame(['horse'], array_keys($groups));
        $this->assertSame(['Name', 'Sex', 'Sire', 'Dam'], $groups['horse']['headers']);
        $this->assertSame([['id' => 1205, 'cells' => ['Al Qous (OM)', 'H', 'Dahess', 'Faurun']]], $groups['horse']['rows']);
    }

    public function test_it_parses_owner_and_jockey_searches(): void
    {
        $parser = new RacingParser();

        $owners = $parser->parseSearch($this->fixture('search_owner'))['owner'];
        $this->assertCount(6, $owners['rows']);
        $this->assertSame(100, $owners['rows'][1]['id']);
        $this->assertSame("Basil bin Mas'oud Al Kasbi", $owners['rows'][1]['cells'][0]);

        $jockeys = $parser->parseSearch($this->fixture('search_jockey'))['jockey'];
        $this->assertSame(['Name', 'Country'], $jockeys['headers']);
        $this->assertSame(128, $jockeys['rows'][0]['id']);
    }

    public function test_no_matches_yields_no_groups(): void
    {
        $parser = new RacingParser();

        $this->assertSame([], $parser->parseSearch($this->fixture('search_empty')));
    }

    public function test_it_parses_a_horse_profile(): void
    {
        $profile = (new RacingParser())->parseProfile($this->fixture('horse'));

        $this->assertSame('Al Qous (OM)', $profile['title']);
        $this->assertSame(['16 yrs', 'Bay', 'H'], $profile['meta']);
        $this->assertSame('(1766)', $profile['days_since_run']);
        $this->assertSame('Dahess - Faurun', $profile['pedigree']);
        $this->assertSame('70', $profile['rating']['value']);
        $this->assertSame(['type' => 'owner', 'id' => 100], $profile['facts'][0]['link']);
        $this->assertSame(['Results', 'Intl. Results', 'Entries', 'Declarations'], array_column($profile['tabs'], 'label'));

        $results = $profile['tabs'][0]['tables'][0];
        $this->assertSame(['Date', 'Course', 'Name', 'Distance', 'Going', 'Result', 'Jockey'], $results['headers']);
        $this->assertCount(34, $results['rows']);
        $this->assertSame(['type' => 'jockey', 'id' => 1], $results['rows'][0][6]['link']);
        $this->assertSame('No upcoming entries', $profile['tabs'][2]['message']);
    }

    public function test_arabic_profile_is_parsed_with_arabic_text(): void
    {
        $profile = (new RacingParser())->parseProfile($this->fixture('horse_ar'));

        $this->assertSame('القوس (عمان)', $profile['title']);
        $this->assertCount(34, $profile['tabs'][0]['tables'][0]['rows']);
    }

    public function test_unknown_horse_is_null(): void
    {
        $this->assertNull((new RacingParser())->parseProfile($this->fixture('horse_missing')));
    }

    public function test_owner_profile_has_image_stats_and_no_graph_tab_or_script_text(): void
    {
        $profile = (new RacingParser())->parseProfile($this->fixture('owner'));

        $this->assertSame('/img/OwnerColours/basil masoud kasbi.jpg', $profile['image']);
        $this->assertSame(['Statistics', 'Recent Results', 'Upcoming Races'], array_column($profile['tabs'], 'label'));
        $this->assertNotEmpty($profile['tabs'][0]['tables']);
        $this->assertStringNotContainsString('cfchart', json_encode($profile));
    }

    public function test_jockey_facts_and_placeholder_image_dropped(): void
    {
        $profile = (new RacingParser())->parseProfile($this->fixture('jockey'));

        $this->assertNull($profile['image']);
        $this->assertSame('MRW:', $profile['facts'][0]['label']);
        $this->assertSame('53.0 kgs', $profile['facts'][0]['value']);
    }

    public function test_empty_trainer_shows_messages_instead_of_tables(): void
    {
        $profile = (new RacingParser())->parseProfile($this->fixture('trainer'));

        $this->assertSame([], $profile['tabs'][0]['tables']);
        $this->assertSame('There are no statistics available for this Trainer', $profile['tabs'][0]['message']);
    }
}
