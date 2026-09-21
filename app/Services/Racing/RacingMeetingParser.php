<?php

namespace App\Services\Racing;

use App\Services\Racing\Concerns\ParsesHtml;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Parses the race-day pages: the meeting "shell" (header + one panel per race) and the
 * per-race fragments the source loads by AJAX (results, entries, race card, form guide).
 */
class RacingMeetingParser
{
    use ParsesHtml;

    private const CLASS_CONTAINS = 'contains(concat(" ", normalize-space(@class), " "), " %s ")';

    /**
     * @return ?array{
     *     heading: ?string,
     *     races: list<array{
     *         id: int, number: ?string, title: ?string, info: list<string>,
     *         facts: list<array{label: string, value: string}>,
     *         conditions: ?array{label: string, value: string},
     *         prizes: list<array{place: string, amount: string}>,
     *         running_time: ?array{label: string, value: string},
     *         video_url: ?string
     *     }>
     * } null when the page is not a race-day shell at all
     */
    public function parseMeeting(string $html): ?array
    {
        $xp = $this->xpath($html);

        if ($xp->query('//h1')->length === 0) {
            return null;
        }

        $races = [];

        foreach ($xp->query('//div[@data-raceid and ' . sprintf(self::CLASS_CONTAINS, 'js-raceTab') . ']') as $panel) {
            /** @var DOMElement $panel */
            $races[] = $this->race($xp, $panel, (int) $panel->getAttribute('data-raceid'));
        }

        return [
            'heading' => $this->firstText($xp, '//div[' . sprintf(self::CLASS_CONTAINS, 'headerWrapper') . ']//p'),
            'races' => $races,
        ];
    }

    /**
     * @param  'results'|'entries'|'card'|'form-guide'  $page
     * @return array<string, mixed> always has `available` (false when the source has nothing for that race)
     */
    public function parseFragment(string $page, string $html): array
    {
        $xp = $this->xpath($html);

        return match ($page) {
            'results' => $this->results($xp),
            'entries' => $this->entries($xp),
            'card' => $this->card($xp),
            'form-guide' => $this->formGuide($xp),
        };
    }

    private function race(DOMXPath $xp, DOMElement $panel, int $id): array
    {
        $details = $xp->query('.//div[' . sprintf(self::CLASS_CONTAINS, 'raceDetails') . ']', $panel)->item(0) ?? $panel;
        $sub = sprintf(self::CLASS_CONTAINS, 'sub');

        $number = $xp->query('//a[@id="tabRaceHeader_' . $id . '"]/span[1]')->item(0);

        $info = array_values(array_filter(array_map(
            fn (DOMNode $s) => $this->text($s),
            iterator_to_array($xp->query('(.//p[' . $sub . '])[1]/span', $details)),
        )));

        $facts = [];
        foreach ($xp->query('.//p[not(@class) and span[' . $sub . ']]/span[' . $sub . ']', $details) as $label) {
            $value = $label->nextSibling instanceof DOMText ? $this->text($label->nextSibling) : '';

            if ($value !== '') {
                $facts[] = ['label' => rtrim($this->text($label)), 'value' => $value];
            }
        }

        $prizes = [];
        foreach ($xp->query('.//ul[' . sprintf(self::CLASS_CONTAINS, 'prizeList') . ']/li', $details) as $li) {
            $place = $this->firstText($xp, './span[' . $sub . ']', $li) ?? '';
            // the amount is the <li>'s own text, next to the place <span>; never strip the place out of the
            // combined string, or "2" + "2200.00" (Arabic shows a bare number) loses its 2s and becomes "00.00"
            $amount = trim(implode(' ', array_map(
                fn (DOMNode $t) => $this->text($t),
                iterator_to_array($xp->query('./text()', $li)),
            )));

            if ($amount !== '') {
                $prizes[] = ['place' => $place, 'amount' => $amount];
            }
        }

        $timeLabel = $xp->query('.//div[' . sprintf(self::CLASS_CONTAINS, 'text_center') . ']/p[' . $sub . ']', $details)->item(0);
        $timeValue = $timeLabel ? $xp->query('following-sibling::p[1]', $timeLabel)->item(0) : null;

        /** @var ?DOMElement $video */
        $video = $xp->query('.//a[' . sprintf(self::CLASS_CONTAINS, 'videoLink') . ' and @href]', $details)->item(0);
        $videoUrl = $video?->getAttribute('href');

        return [
            'id' => $id,
            'number' => $number ? $this->text($number) : null,
            'title' => $this->firstText($xp, './/p[' . sprintf(self::CLASS_CONTAINS, 'title') . ']', $details),
            'info' => $info,
            'facts' => $facts,
            'conditions' => $this->conditions($xp, $details),
            'prizes' => $prizes,
            'running_time' => $timeLabel && $timeValue ? ['label' => $this->text($timeLabel), 'value' => $this->text($timeValue)] : null,
            // only ever hand a YouTube link to the templates
            'video_url' => $videoUrl && preg_match('#^https://(www\.)?(youtube\.com|youtu\.be)/#i', $videoUrl) ? $videoUrl : null,
        ];
    }

    /** Conditions keep their line breaks (weights / allowances are one clause per line). */
    private function conditions(DOMXPath $xp, DOMNode $details): ?array
    {
        $p = $xp->query('.//p[' . sprintf(self::CLASS_CONTAINS, 'margin_top') . ' and span[' . sprintf(self::CLASS_CONTAINS, 'sub') . ']]', $details)->item(0);

        if (! $p) {
            return null;
        }

        $label = $this->firstText($xp, './span[' . sprintf(self::CLASS_CONTAINS, 'sub') . ']', $p) ?? '';
        $raw = str_replace(["\xC2\xA0", "\r"], [' ', ''], $p->textContent);
        $raw = preg_replace('/[ \t]+/', ' ', $raw) ?? $raw;

        $value = implode("\n", array_filter(array_map('trim', explode("\n", trim(preg_replace('/^\s*' . preg_quote($label, '/') . '/u', '', trim($raw)) ?? $raw)))));

        return $value !== '' ? ['label' => rtrim($label), 'value' => $value] : null;
    }

    private function results(DOMXPath $xp): array
    {
        $table = $xp->query('//table[starts-with(@id, "tblRaceResults")]')->item(0);

        if (! $table) {
            return ['available' => false];
        }

        $owners = [];
        foreach ($xp->query('//a[' . sprintf(self::CLASS_CONTAINS, 'popOwner') . ' and @rel]') as $a) {
            /** @var DOMElement $a */
            $n = $xp->query('preceding-sibling::span[1]', $a)->item(0);

            $owners[] = ['n' => $n ? rtrim($this->text($n), '.') : null, 'id' => (int) $a->getAttribute('rel'), 'name' => $this->text($a)];
        }

        return [
            'available' => true,
            'table' => $this->tableData($xp, $table),
            'owners' => $owners,
            'overweights' => $this->firstText($xp, '//p[' . sprintf(self::CLASS_CONTAINS, 'text_dark') . ' and ' . sprintf(self::CLASS_CONTAINS, 'text_sub') . ']'),
        ];
    }

    private function entries(DOMXPath $xp): array
    {
        $table = $xp->query('//table[starts-with(@id, "tblEntry")]')->item(0);
        $data = $table ? $this->tableData($xp, $table) : null;

        return $data && $data['rows'] ? ['available' => true, 'table' => $data] : ['available' => false];
    }

    private function card(DOMXPath $xp): array
    {
        $runners = [];

        foreach ($xp->query('//table[starts-with(@id, "tblEntry")]/tbody/tr') as $tr) {
            $cells = iterator_to_array($xp->query('./td', $tr));

            if (count($cells) < 5) {
                continue;
            }

            $weight = array_map(fn (DOMNode $p) => $this->text($p), iterator_to_array($xp->query('.//p', $cells[count($cells) - 1])));

            $runners[] = ['no' => $this->text($cells[0]), 'gate' => $this->text($cells[1])] + $this->runnerProfile($xp, $tr) + [
                'weight' => $weight[0] ?? $this->text($cells[count($cells) - 1]),
                'weight_note' => $weight[1] ?? null,
            ];
        }

        return $runners ? ['available' => true, 'runners' => $runners] : ['available' => false];
    }

    private function formGuide(DOMXPath $xp): array
    {
        $runners = [];

        foreach ($xp->query('//div[' . sprintf(self::CLASS_CONTAINS, 'formGuide') . ']') as $block) {
            $table = $xp->query('.//table[' . sprintf(self::CLASS_CONTAINS, 'formGuideTable') . ']', $block)->item(0);

            $runners[] = ['no' => $this->firstText($xp, './/div[' . sprintf(self::CLASS_CONTAINS, 'saddleCloth') . ']', $block)]
                + $this->runnerProfile($xp, $block)
                + ['runs' => $table ? $this->tableData($xp, $table) : ['headers' => [], 'rows' => []]];
        }

        return $runners ? ['available' => true, 'runners' => $runners] : ['available' => false];
    }

    /** The horse / owner / trainer / jockey block shared by the race card and the form guide. */
    private function runnerProfile(DOMXPath $xp, DOMNode $context): array
    {
        /** @var ?DOMElement $img */
        $img = $xp->query('.//img[@src]', $context)->item(0);

        $meta = array_map(
            fn (DOMNode $s) => $this->text($s),
            iterator_to_array($xp->query('.//p/span[' . sprintf(self::CLASS_CONTAINS, 'text_sub') . ']', $context)),
        );

        return [
            'horse' => $this->entity($xp, $context, 'popHorse'),
            'meta' => array_values(array_filter($meta)),
            'record' => $this->firstText($xp, './/p/span[' . sprintf(self::CLASS_CONTAINS, 'margin_right') . ' and not(' . sprintf(self::CLASS_CONTAINS, 'text_sub') . ') and not(' . sprintf(self::CLASS_CONTAINS, 'horseName') . ')]', $context),
            'owner' => $this->entity($xp, $context, 'popOwner'),
            'trainer' => $this->entity($xp, $context, 'popTrainer'),
            'jockey' => $this->entity($xp, $context, 'popJockey'),
            'image' => $this->imagePath($img?->getAttribute('src')),
        ];
    }
}
