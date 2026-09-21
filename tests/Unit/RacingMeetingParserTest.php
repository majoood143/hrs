<?php

namespace Tests\Unit;

use App\Services\Racing\RacingHandicapParser;
use App\Services\Racing\RacingMeetingParser;
use App\Services\Racing\RacingUnavailableException;
use PHPUnit\Framework\TestCase;

class RacingMeetingParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . "/../Fixtures/racing/{$name}");
    }

    private function meeting(string $name): array
    {
        return (new RacingMeetingParser())->parseMeeting($this->fixture($name));
    }

    private function fragment(string $page, string $name): array
    {
        return (new RacingMeetingParser())->parseFragment($page, $this->fixture($name));
    }

    public function test_meeting_header_and_all_races(): void
    {
        $meeting = $this->meeting('meeting_results_9178.html');

        $this->assertSame('Al Rahba , Friday, 5th Meeting-National Day Cups, 19 Nov 2021', $meeting['heading']);
        $this->assertSame([9128, 9172, 9173, 9174, 9175, 9176, 9177, 9178], array_column($meeting['races'], 'id'));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8'], array_column($meeting['races'], 'number'));
    }

    public function test_race_details(): void
    {
        $race = collect($this->meeting('meeting_results_9178.html')['races'])->firstWhere('id', 9178);

        $this->assertSame('National Museum 51st National Day Cup', $race['title']);
        $this->assertSame(['17:00', '1600M', 'Purebred Arabian', 'Stakes'], $race['info']);
        $this->assertSame(['Surface:' => 'Dirt', 'Weather:' => 'Fine', 'Rail Position:' => 'True'], array_column($race['facts'], 'value', 'label'));
        $this->assertSame("(Purebred Arabian-Local Bred)\n3YO&+ Open: Weight-for-Age", $race['conditions']['value']);
        $this->assertSame([['place' => '1st', 'amount' => '4500.00'], ['place' => '2nd', 'amount' => '2200.00']], array_slice($race['prizes'], 0, 2));
        $this->assertSame('1:51:15', $race['running_time']['value']);
        $this->assertSame('https://www.youtube.com/watch?v=1DFTS1ySgIY', $race['video_url']);
    }

    public function test_upcoming_race_has_no_running_time_or_video(): void
    {
        $meeting = $this->meeting('meeting_entries_latest.html');

        $this->assertSame('Al Rahba , Saturday, 1st Meeting, 17 Oct 2026', $meeting['heading']);
        $this->assertNull($meeting['races'][0]['running_time']);
        $this->assertNull($meeting['races'][0]['video_url']);
        $this->assertStringContainsString('(Apprentice Claims)', $meeting['races'][0]['conditions']['value']);
    }

    public function test_arabic_meeting_keeps_arabic_labels(): void
    {
        $race = $this->meeting('meeting_results_9178_ar.html')['races'][0];

        $this->assertStringContainsString('متحف بيت البرندة', $race['title']);
        $this->assertSame('الأرضية:', $race['facts'][0]['label']);
    }

    public function test_arabic_prizes_keep_amounts_that_contain_the_place_number(): void
    {
        // Arabic labels the places "1", "2", ... (English says "1st", "2nd"), and 2200.00 contains a 2
        $race = collect($this->meeting('meeting_results_9178_ar.html')['races'])->firstWhere('id', 9178);

        $this->assertSame([
            ['place' => '1', 'amount' => '4500.00'],
            ['place' => '2', 'amount' => '2200.00'],
            ['place' => '3', 'amount' => '1600.00'],
            ['place' => '4', 'amount' => '1000.00'],
            ['place' => '5', 'amount' => '700.00'],
        ], $race['prizes']);
    }

    public function test_english_and_arabic_prize_amounts_match_for_every_race(): void
    {
        $amounts = fn (array $meeting) => array_map(fn ($r) => array_column($r['prizes'], 'amount'), $meeting['races']);

        $this->assertSame($amounts($this->meeting('meeting_results_9178.html')), $amounts($this->meeting('meeting_results_9178_ar.html')));
    }

    public function test_only_youtube_links_survive(): void
    {
        // the source entity-escapes attribute values, so swap the whole href rather than the URL text
        $html = preg_replace('/(class="videoLink[^>]*?href=")[^"]*(")/s', '$1javascript:alert(1)$2', $this->fixture('meeting_results_9178.html'), -1, $count);
        $this->assertGreaterThan(0, $count, 'fixture has video links to tamper with');

        $race = collect((new RacingMeetingParser())->parseMeeting($html)['races'])->firstWhere('id', 9178);

        $this->assertNull($race['video_url']);
    }

    public function test_results_fragment(): void
    {
        $detail = $this->fragment('results', 'fragment_results_9178.html');

        $this->assertTrue($detail['available']);
        $this->assertSame(['FP', 'Margin', 'No', 'Gate', 'Horse', 'Wgt (Kgs)', 'Equipment', 'Trainer', 'Jockey'], $detail['table']['headers']);
        $this->assertCount(16, $detail['table']['rows']);

        $first = $detail['table']['rows'][0];
        $this->assertSame(['type' => 'horse', 'id' => 3216], $first[4]['link']);
        $this->assertSame('56.0 (OR 62)', $first[5]['text']);
        $this->assertSame(['type' => 'trainer', 'id' => 176], $first[7]['link']);
        $this->assertSame(['type' => 'jockey', 'id' => 88], $first[8]['link']);

        $this->assertCount(16, $detail['owners']);
        $this->assertSame(['n' => '1', 'id' => 140, 'name' => 'Hamood bin Said Al Nahdi'], $detail['owners'][0]);
        $this->assertStringContainsString('Abrar Muscat (OM) (56.5)', $detail['overweights']);
    }

    public function test_entries_fragment(): void
    {
        $detail = $this->fragment('entries', 'fragment_entries_9178.html');

        $this->assertSame(['HR', 'Horse', 'Wgt (Kgs)', 'Owner', 'Trainer'], $detail['table']['headers']);
        $this->assertCount(26, $detail['table']['rows']);
        $this->assertSame(['type' => 'owner', 'id' => 681], $detail['table']['rows'][0][3]['link']);
    }

    public function test_race_card_fragment(): void
    {
        $detail = $this->fragment('card', 'fragment_card_9178.html');

        $this->assertCount(19, $detail['runners']);
        $this->assertSame([
            'no' => '1',
            'gate' => '9',
            'horse' => ['id' => 3125, 'name' => 'S.s.r. Alfaseeh (OM)'],
            'meta' => ['6yrs', 'Ch', 'H'],
            'record' => '27 (7-5-4)',
            'owner' => ['id' => 681, 'name' => 'Khalid bin Mohamed Al Falahi'],
            'trainer' => ['id' => 53, 'name' => 'Hassan bin Mohamed Al Falahi'],
            'jockey' => ['id' => 26, 'name' => 'Amur bin Ali Al Rasbi'],
            'image' => '/img/OwnerColours/khalid mohamed falahi.jpg',
            'weight' => '58.0',
            'weight_note' => '(OR 83)',
        ], $detail['runners'][0]);
    }

    public function test_form_guide_fragment(): void
    {
        $detail = $this->fragment('form-guide', 'fragment_form-guide_9178.html');

        $this->assertCount(19, $detail['runners']);

        $runner = $detail['runners'][0];
        $this->assertSame('1', $runner['no']);
        $this->assertSame(3125, $runner['horse']['id']);
        $this->assertSame(['Date', 'Crs', 'Surf', 'Dist', 'TC', 'Type', 'Jockey', 'DR', 'Wgt', 'FP/Rn', 'Beaten By', 'HR'], $runner['runs']['headers']);
        $this->assertCount(4, $runner['runs']['rows']);
        $this->assertSame('Length: 12.1 Wgt: 58.0', $runner['runs']['rows'][0][10]['title']);
    }

    public function test_pages_with_nothing_yet_are_not_available(): void
    {
        foreach (['results', 'entries', 'card', 'form-guide'] as $page) {
            $this->assertSame(['available' => false], $this->fragment($page, 'fragment_unavailable.html'), $page);
        }
    }

    public function test_handicap_grid(): void
    {
        $horses = (new RacingHandicapParser())->parse($this->fixture('handicap.json'));
        $byId = array_column($horses, null, 'id');

        $this->assertCount(31, $horses);
        $this->assertSame(['id' => 1205, 'name' => 'Al Qous (OM)', 'rating' => 70, 'label' => null, 'change' => -5, 'rating_date' => '2021-11-21', 'last_ran' => '2021-11-19'], $byId[1205]);

        $nor = collect($horses)->firstWhere('label', 'NOR');
        $this->assertNull($nor['rating']);

        $blank = collect($horses)->first(fn ($h) => $h['rating'] === null && $h['label'] === null);
        $this->assertNull($blank['rating_date']);

        $this->assertNotEmpty(array_filter($horses, fn ($h) => $h['change'] > 0), 'ratingPos rows parse as positive changes');
    }

    public function test_handicap_grid_with_no_rows_or_an_error_page(): void
    {
        $parser = new RacingHandicapParser();

        $this->assertSame([], $parser->parse('{"TOTAL":0,"RECORDS":0,"PAGE":0,"ROWS":""}'));

        $this->expectException(RacingUnavailableException::class);
        $parser->parse('<script>parent.window.$(function() { showError(); });</script>');
    }
}
