<?php

namespace App\Services\Racing\Concerns;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * DOM helpers shared by the racing parsers. The source is third-party ColdFusion HTML,
 * so we only ever extract text / ids / whitelisted paths out of it.
 */
trait ParsesHtml
{
    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // Inline scripts (ColdFusion chart bootstrap, tab wiring) must never leak into text().
        foreach (['script', 'style', 'noscript'] as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        return new DOMXPath($doc);
    }

    private function text(DOMNode $node): string
    {
        return trim(preg_replace('/[\s\x{A0}]+/u', ' ', $node->textContent) ?? '');
    }

    private function firstText(DOMXPath $xp, string $query, ?DOMNode $context = null): ?string
    {
        $node = $xp->query($query, $context)->item(0);
        $text = $node ? $this->text($node) : '';

        return $text !== '' ? $text : null;
    }

    /** @return ?array{type: string, id: int} first entity link (horse/jockey/owner/trainer/race) inside $context */
    private function link(DOMXPath $xp, DOMNode $context): ?array
    {
        foreach ($xp->query('.//a[@rel and @class]', $context) as $a) {
            /** @var DOMElement $a */
            if (preg_match('/\b(?:altPop|pop)(Race|Horse|Jockey|Owner|Trainer)\b/', $a->getAttribute('class'), $m) && (int) $a->getAttribute('rel') > 0) {
                return ['type' => strtolower($m[1]), 'id' => (int) $a->getAttribute('rel')];
            }
        }

        return null;
    }

    /** @return ?array{id: int, name: string} the entity link matching a class (e.g. popHorse) inside $context */
    private function entity(DOMXPath $xp, DOMNode $context, string $class): ?array
    {
        /** @var ?DOMElement $a */
        $a = $xp->query('.//a[@rel and contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]', $context)->item(0);

        if (! $a || (int) $a->getAttribute('rel') < 1) {
            return null;
        }

        return ['id' => (int) $a->getAttribute('rel'), 'name' => $this->text($a)];
    }

    /** Image path on the source (always /img/...), or null for missing / placeholder art. */
    private function imagePath(?string $src): ?string
    {
        if ($src === null || $src === '') {
            return null;
        }

        $path = preg_replace('#/+#', '/', (string) parse_url($src, PHP_URL_PATH));

        if (! str_starts_with($path, '/img/') || stripos($path, 'unknown') !== false) {
            return null;
        }

        return $path;
    }

    /** @return array{headers: list<string>, rows: list<list<array{text: string, link: ?array{type: string, id: int}, title: ?string}>>} */
    private function tableData(DOMXPath $xp, DOMNode $table): array
    {
        return [
            'headers' => array_map(fn (DOMNode $th) => $this->text($th), iterator_to_array($xp->query('.//thead//th', $table))),
            'rows' => $this->tableRows($xp, $table),
        ];
    }

    private function tableRows(DOMXPath $xp, DOMNode $table): array
    {
        $rows = [];

        foreach ($xp->query('.//tbody/tr', $table) as $tr) {
            $row = [];

            foreach ($xp->query('./td', $tr) as $td) {
                /** @var DOMElement $td */
                $title = trim(preg_replace('/\s+/u', ' ', $td->getAttribute('title')) ?? '');

                $row[] = ['text' => $this->text($td), 'link' => $this->link($xp, $td), 'title' => $title !== '' ? $title : null];
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
