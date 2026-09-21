<?php

namespace App\Services\Racing;

use App\Services\Racing\Concerns\ParsesHtml;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns the racing source's HTML into plain arrays.
 *
 * We deliberately extract data instead of passing the source's markup through:
 * it is third-party HTML with its own scripts, inline handlers and http:// assets.
 */
class RacingParser
{
    use ParsesHtml;

    /**
     * @return array<string, array{headers: list<string>, rows: list<array{id: int, cells: list<string>}>}>
     *         keyed by group (horse|owner|jockey|trainer); empty when nothing matched
     */
    public function parseSearch(string $html): array
    {
        $xp = $this->xpath($html);
        $groups = [];

        foreach ($xp->query('//table[starts-with(@id, "tbl") and substring(@id, string-length(@id) - 6) = "Results"]') as $table) {
            /** @var DOMElement $table */
            $key = strtolower(substr($table->getAttribute('id'), 3, -7));
            $rows = [];

            foreach ($xp->query('.//tbody/tr', $table) as $tr) {
                /** @var ?DOMElement $link */
                $link = $xp->query('.//a[@rel]', $tr)->item(0);
                $id = $link ? (int) $link->getAttribute('rel') : 0;

                if ($id < 1) {
                    continue;
                }

                $rows[] = [
                    'id' => $id,
                    'cells' => array_map(fn (DOMNode $td) => $this->text($td), iterator_to_array($xp->query('./td', $tr))),
                ];
            }

            if ($rows !== []) {
                $groups[$key] = [
                    'headers' => array_map(fn (DOMNode $th) => $this->text($th), iterator_to_array($xp->query('.//thead//th', $table))),
                    'rows' => $rows,
                ];
            }
        }

        return $groups;
    }

    /**
     * Parse a *_GenWin.cfm detail fragment (horse, owner, jockey or trainer).
     *
     * @return array{
     *     title: string,
     *     image: ?string,
     *     meta: list<string>,
     *     days_since_run: ?string,
     *     pedigree: ?string,
     *     rating: ?array{value: string, label: string},
     *     facts: list<array{label: string, value: string, link: ?array{type: string, id: int}}>,
     *     tabs: list<array{label: string, tables: list<array{headers: list<string>, rows: list<list<array{text: string, link: ?array{type: string, id: int}}>>}>, message: ?string}>
     * }|null null when the source has no such record (it answers 200 with an empty title)
     */
    public function parseProfile(string $html): ?array
    {
        $xp = $this->xpath($html);

        $title = $xp->query('//h1[contains(concat(" ", normalize-space(@class), " "), " title ")]')->item(0);
        $title = $title ? $this->text($title) : '';

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'image' => $this->image($xp),
            'meta' => $this->meta($xp),
            'days_since_run' => $this->firstText($xp, '//div[contains(concat(" ", normalize-space(@class), " "), " pop ")]//p[contains(@class, "margin_top_small")]/span[contains(@class, "text_sub")]'),
            'pedigree' => $this->pedigree($xp),
            'rating' => $this->rating($xp),
            'facts' => $this->facts($xp),
            'tabs' => $this->tabs($xp),
        ];
    }

    private function image(DOMXPath $xp): ?string
    {
        /** @var ?DOMElement $img */
        $img = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " pop ")]//img[@src]')->item(0);

        return $this->imagePath($img?->getAttribute('src'));
    }

    /** @return list<string> age / colour / sex spans under the horse name */
    private function meta(DOMXPath $xp): array
    {
        $spans = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " pop ")]//p[contains(@class, "margin_top_small")]/span[not(contains(@class, "text_sub"))]');

        return array_values(array_filter(array_map(fn (DOMNode $s) => $this->text($s), iterator_to_array($spans))));
    }

    private function pedigree(DOMXPath $xp): ?string
    {
        $text = $this->firstText($xp, '//div[contains(concat(" ", normalize-space(@class), " "), " pop ")]//p[contains(concat(" ", normalize-space(@class), " "), " text_sub ")]');

        return in_array($text, [null, '-'], true) ? null : $text;
    }

    /** @return ?array{value: string, label: string} */
    private function rating(DOMXPath $xp): ?array
    {
        $block = $xp->query('//div[contains(@class, "isoBlock")]')->item(0);

        if (! $block) {
            return null;
        }

        $node = $xp->query('.//p', $block)->item(0);
        $value = $node ? $this->text($node) : '';

        $label = $xp->query('.//em', $block)->item(0);

        return $value !== '' ? ['value' => $value, 'label' => $label ? $this->text($label) : ''] : null;
    }

    /** Two-column "Label: value" rows in the header (owner, trainer, jockey MRW, ...). */
    private function facts(DOMXPath $xp): array
    {
        $facts = [];

        foreach ($xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " pop ")]//table//tr[count(td) = 2]') as $tr) {
            $cells = iterator_to_array($xp->query('./td', $tr));
            $label = $this->text($cells[0]);
            $value = $this->text($cells[1]);
            $link = $this->link($xp, $cells[1]);

            if ($label !== '' && ($value !== '' || $link)) {
                $facts[] = ['label' => $label, 'value' => $value, 'link' => $link];
            }
        }

        return $facts;
    }

    private function tabs(DOMXPath $xp): array
    {
        $tabs = [];

        foreach ($xp->query('//ul//a[starts-with(@href, "#tabPop")]') as $a) {
            /** @var DOMElement $a */
            $panelId = substr($a->getAttribute('href'), 1);

            // Owner / jockey / trainer pages end with a "Graphs" tab that is a
            // session-bound ColdFusion chart PNG we cannot serve, so skip it.
            if (str_starts_with($panelId, 'tabPopRaceInfo_') && str_ends_with($panelId, '_4')) {
                continue;
            }

            $panel = $xp->query('//*[@id="' . $panelId . '"]')->item(0);

            if (! $panel) {
                continue;
            }

            $label = $xp->query('.//span[contains(@class, "margin_left")]', $a)->item(0);
            $tables = [];

            foreach ($xp->query('.//table', $panel) as $table) {
                $tables[] = $this->tableData($xp, $table);
            }

            $tabs[] = [
                'label' => $this->text($label ?? $a),
                'tables' => $tables,
                'message' => $tables === [] ? ($this->text($panel) ?: null) : null,
            ];
        }

        return $tabs;
    }
}
