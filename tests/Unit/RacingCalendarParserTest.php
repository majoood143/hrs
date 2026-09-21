<?php

namespace Tests\Unit;

use App\Services\Racing\RacingCalendarParser;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use PHPUnit\Framework\TestCase;

class RacingCalendarParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . "/../Fixtures/racing/{$name}");
    }

    public function test_seasons_come_from_the_source_newest_first(): void
    {
        $seasons = (new RacingCalendarParser())->parseSeasons($this->fixture('calendar_page.html'));

        $this->assertCount(16, $seasons);
        $this->assertSame(['26/27', '25/26', '24/25'], array_slice($seasons, 0, 3));
        $this->assertSame('11/12', end($seasons));
    }

    public function test_no_season_select_means_no_seasons(): void
    {
        $this->assertSame([], (new RacingCalendarParser())->parseSeasons('<html><body><p>changed markup</p></body></html>'));
    }

    public function test_race_days_are_grouped_by_date_with_the_meeting_name(): void
    {
        $days = (new RacingCalendarParser())->parseDays($this->fixture('calendar_days_2627.json'));

        $this->assertCount(25, $days, '53 races fall on 25 race days');
        $this->assertSame('2026-10-17', array_key_first($days));
        $this->assertSame('1st Meeting', $days['2026-10-17']);
        $this->assertSame('2027-04-17', array_key_last($days));
        $this->assertSame('Final Meeting', $days['2027-04-17']);

        $dates = array_keys($days);
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'oldest first');
    }

    public function test_meeting_names_with_extras_are_kept_verbatim(): void
    {
        $days = (new RacingCalendarParser())->parseDays('{"RACES":{"1":[[2026,11,21],["6th Meeting-National Day Cups"]],"2":[[2027,2,10],["16th Meeting (Night)"]],"3":[[2027,2,10],["16th Meeting (Night)"]]}}');

        $this->assertSame(['2026-11-21' => '6th Meeting-National Day Cups', '2027-02-10' => '16th Meeting (Night)'], $days);
    }

    public function test_an_empty_range_and_impossible_dates(): void
    {
        $parser = new RacingCalendarParser();

        $this->assertSame([], $parser->parseDays('[]'));
        $this->assertSame([], $parser->parseDays('{"RACES":{"1":[[2026,2,31],["Nope"]],"2":[[0,0,0],["Nope"]]}}'));
    }

    public function test_an_error_page_is_unavailable_not_an_empty_calendar(): void
    {
        $this->expectException(RacingUnavailableException::class);

        (new RacingCalendarParser())->parseDays($this->fixture('calendar_error.html'));
    }

    public function test_date_links(): void
    {
        $parser = new RacingCalendarParser();

        $this->assertSame(['page' => 'entries', 'race' => 10005], $parser->parseLink($this->fixture('calendar_link_entries.json')));
        $this->assertSame(['page' => 'results', 'race' => 9822], $parser->parseLink($this->fixture('calendar_link_results.json')));
        $this->assertNull($parser->parseLink($this->fixture('calendar_link_none.json')), 'a date with no meeting is `""`');
        $this->assertNull($parser->parseLink('{"PAGE":"?page=RaceEntry&raceFile=0"}'));
    }

    public function test_a_date_link_error_page_is_unavailable(): void
    {
        $this->expectException(RacingUnavailableException::class);

        (new RacingCalendarParser())->parseLink($this->fixture('calendar_error.html'));
    }

    public function test_season_ranges_run_october_to_september(): void
    {
        $this->assertSame(['from' => '2025-10-01', 'to' => '2026-09-30'], RacingClient::seasonRange('25/26'));
        $this->assertSame(['from' => '2011-10-01', 'to' => '2012-09-30'], RacingClient::seasonRange('11/12'));
        $this->assertSame(['from' => '1999-10-01', 'to' => '2000-09-30'], RacingClient::seasonRange('99/00'));
    }

    public function test_every_date_maps_to_its_season(): void
    {
        $this->assertSame('26/27', RacingClient::seasonOf('2026-10-01'));
        $this->assertSame('26/27', RacingClient::seasonOf('2027-04-17'));
        $this->assertSame('26/27', RacingClient::seasonOf('2027-09-30'));
        $this->assertSame('25/26', RacingClient::seasonOf('2026-09-30'));
        $this->assertSame('25/26', RacingClient::seasonOf('2025-12-31'));
        $this->assertSame('99/00', RacingClient::seasonOf('2000-01-05'));
    }

    public function test_source_pages_map_to_our_pages(): void
    {
        $this->assertSame('results', RacingClient::pageKey('?page=RaceResults&raceFile=1'));
        $this->assertSame('entries', RacingClient::pageKey('?page=RaceEntry&raceFile=1'));
        $this->assertSame('card', RacingClient::pageKey('?page=RaceCard&raceFile=1'));
        $this->assertSame('form-guide', RacingClient::pageKey('?page=RaceFormGuide&raceFile=1'));
        $this->assertSame('results', RacingClient::pageKey('anything else'));
    }

    public function test_a_race_day_is_parsed_into_races_with_the_links_that_exist(): void
    {
        $races = (new RacingCalendarParser())->parseRaces($this->fixture('day_2026-04-02.json'));

        $this->assertCount(9, $races);
        $this->assertSame(9818, $races[0]['id']);
        $this->assertSame(1200, $races[0]['distance']);
        $this->assertSame('13:30', $races[0]['time']);
        $this->assertSame('21st Meeting (RESCHEDULED 2-4-26)', $races[0]['meeting']);
        $this->assertSame('13:30', $races[0]['meeting_time']);
        $this->assertMatchesRegularExpression('#^https://youtu\.be/\S+$#', $races[0]['video']);
        $this->assertNull($races[0]['photo']); // the disabled icon is not a link
        $this->assertTrue($races[0]['entries'] && $races[0]['card'] && $races[0]['results']);

        // race 9968 has entries and a card but no result yet
        $this->assertSame(9968, $races[2]['id']);
        $this->assertTrue($races[2]['entries'] && $races[2]['card']);
        $this->assertFalse($races[2]['results']);
    }

    public function test_an_upcoming_day_has_races_but_no_links_at_all(): void
    {
        $races = (new RacingCalendarParser())->parseRaces($this->fixture('day_2026-10-17.json'));

        $this->assertCount(8, $races);
        $this->assertSame('3YO&+ R48-NOR', $races[1]['name']); // raw text, escaped when rendered
        $this->assertSame('14:00', $races[1]['time']);

        foreach ($races as $race) {
            $this->assertNull($race['video']);
            $this->assertNull($race['photo']);
            $this->assertFalse($race['entries'] || $race['card'] || $race['results']);
        }
    }

    public function test_video_and_photo_links_are_cleaned_and_only_kept_when_they_are_plain_web_urls(): void
    {
        $row = fn (string $video, string $photo) => json_encode(['ROWS' => [[1, 'R', 1200.0, $video, $photo, '', '', '', 'M', '13:30', '13:30']]]);
        $parser = new RacingCalendarParser();

        // some source rows carry a trailing carriage return inside the href
        $this->assertSame('https://youtu.be/abc', $parser->parseRaces($row("<a class='videoLink' href='https://youtu.be/abc\r' rel='1'>x</a>", ''))[0]['video']);
        $this->assertNull($parser->parseRaces($row("<a href='javascript:alert(1)'>x</a>", ''))[0]['video']);
        $this->assertNull($parser->parseRaces($row("<a href='data:text/html,x'>x</a>", ''))[0]['video']);
        $this->assertNull($parser->parseRaces($row('', "<a href=\"https://x.test/a b\">x</a>"))[0]['photo']);
    }

    public function test_an_empty_day_is_no_races_and_anything_else_is_an_outage(): void
    {
        $parser = new RacingCalendarParser();

        $this->assertSame([], $parser->parseRaces('{"TOTAL":0,"RECORDS":0,"PAGE":0,"ROWS":""}'));

        $this->expectException(RacingUnavailableException::class);
        $parser->parseRaces('<script>showError()</script>');
    }
}
