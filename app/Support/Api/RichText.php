<?php

namespace App\Support\Api;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Admin-written rich text (HTML) as plain blocks the app draws natively: headings, paragraphs,
 * list items and quotes, with their text only. Markup, scripts and styles never reach the app.
 */
class RichText
{
    private const BLOCK_TAGS = [
        'h1' => 'heading', 'h2' => 'heading', 'h3' => 'heading', 'h4' => 'subheading', 'h5' => 'subheading', 'h6' => 'subheading',
        'p' => 'paragraph', 'li' => 'list_item', 'blockquote' => 'quote', 'pre' => 'paragraph',
    ];

    /**
     * @return list<array{type: string, text: string}>
     */
    public static function blocks(?string $html): array
    {
        if (blank(strip_tags((string) $html))) {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $blocks = [];
        $loose = '';

        $flush = function () use (&$blocks, &$loose) {
            if (($text = self::clean($loose)) !== '') {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
            }
            $loose = '';
        };

        $walk = function (DOMNode $node) use (&$walk, &$blocks, &$loose, $flush) {
            foreach ($node->childNodes as $child) {
                $tag = $child instanceof DOMElement ? strtolower($child->tagName) : null;

                if (in_array($tag, ['script', 'style', 'iframe', 'template'], true)) {
                    continue;
                }

                if ($tag && isset(self::BLOCK_TAGS[$tag]) && ! self::hasBlockChildren($child)) {
                    $flush();

                    if (($text = self::clean(self::text($child))) !== '') {
                        $blocks[] = ['type' => self::BLOCK_TAGS[$tag], 'text' => $text];
                    }

                    continue;
                }

                if ($tag === 'br') {
                    $loose .= "\n";
                } elseif ($tag && in_array($tag, ['div', 'section', 'article', 'ul', 'ol', 'blockquote', 'li', 'figure', 'table', 'tbody', 'tr', 'td', 'th', 'body'], true)) {
                    $flush();
                    $walk($child);
                    $flush();
                } elseif ($child->nodeType === XML_TEXT_NODE || $tag) {
                    $loose .= self::text($child);
                }
            }
        };

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body) {
            $walk($body);
            $flush();
        }

        return $blocks;
    }

    private static function hasBlockChildren(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && (isset(self::BLOCK_TAGS[strtolower($child->tagName)]) || in_array(strtolower($child->tagName), ['ul', 'ol', 'div'], true))) {
                return true;
            }
        }

        return false;
    }

    /** Text of a node, with <br> kept as line breaks. */
    private static function text(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= $child instanceof DOMElement && strtolower($child->tagName) === 'br' ? "\n" : self::text($child);
        }

        return $text;
    }

    private static function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\n */u', "\n", $text);

        return trim($text);
    }
}
