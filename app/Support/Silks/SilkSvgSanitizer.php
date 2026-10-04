<?php

namespace App\Support\Silks;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pattern markup is typed by admins and printed raw into public pages and downloads, so it is
 * rebuilt from an allow list: plain shapes and geometry only, colours only "currentColor" (the
 * pattern colour the visitor picks) or "none". Anything else is dropped and reported.
 */
final class SilkSvgSanitizer
{
    public const MAX_LENGTH = 30000;

    private const ELEMENTS = ['g', 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line'];

    private const NUMBER = '/^-?\d*\.?\d+(e-?\d+)?$/i';

    private const LIST = '/^[-\d.,\se]+$/i';

    /** @var array<string, string> attribute => regex its value must match */
    private const ATTRIBUTES = [
        'x' => self::NUMBER, 'y' => self::NUMBER, 'width' => self::NUMBER, 'height' => self::NUMBER,
        'cx' => self::NUMBER, 'cy' => self::NUMBER, 'r' => self::NUMBER, 'rx' => self::NUMBER, 'ry' => self::NUMBER,
        'x1' => self::NUMBER, 'y1' => self::NUMBER, 'x2' => self::NUMBER, 'y2' => self::NUMBER,
        'points' => self::LIST,
        'd' => '/^[MmLlHhVvCcSsQqTtAaZz\d.,\se-]+$/',
        'transform' => '/^((translate|rotate|scale|skewX|skewY|matrix)\([-\d.,\se]*\)\s*)+$/',
        'fill' => '/^(currentColor|none)$/',
        'stroke' => '/^(currentColor|none)$/',
        'stroke-width' => self::NUMBER,
        'stroke-linecap' => '/^(butt|round|square)$/',
        'stroke-linejoin' => '/^(miter|round|bevel)$/',
        'fill-rule' => '/^(nonzero|evenodd)$/',
        'opacity' => self::NUMBER,
    ];

    /**
     * @return array{svg: ?string, dropped: list<string>} svg is null when the markup is not
     *                                                    well-formed or holds no shape at all
     */
    public static function sanitize(?string $markup): array
    {
        $markup = trim((string) $markup);

        if ($markup === '' || strlen($markup) > self::MAX_LENGTH) {
            return ['svg' => null, 'dropped' => []];
        }

        $source = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $source->loadXML('<svg>'.$markup.'</svg>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $source->doctype !== null) {
            return ['svg' => null, 'dropped' => []];
        }

        $target = new DOMDocument;
        $dropped = [];
        $out = '';

        foreach ($source->documentElement->childNodes as $child) {
            $clean = self::copy($child, $target, $dropped);

            if ($clean) {
                $out .= $target->saveXML($clean);
            }
        }

        return ['svg' => $out === '' ? null : $out, 'dropped' => array_values(array_unique($dropped))];
    }

    /** The sanitized markup, or '' when nothing usable is left. */
    public static function clean(?string $markup): string
    {
        return self::sanitize($markup)['svg'] ?? '';
    }

    private static function copy(DOMNode $node, DOMDocument $target, array &$dropped): ?DOMElement
    {
        if (! $node instanceof DOMElement) {
            if ($node->nodeType !== XML_TEXT_NODE || trim($node->textContent) !== '') {
                $dropped[] = $node->nodeType === XML_TEXT_NODE ? 'text' : $node->nodeName;
            }

            return null;
        }

        $name = $node->localName;

        if (! in_array($name, self::ELEMENTS, true) || $node->namespaceURI !== null) {
            $dropped[] = '<'.$node->nodeName.'>';

            return null;
        }

        $element = $target->createElement($name);

        foreach ($node->attributes as $attribute) {
            $pattern = self::ATTRIBUTES[$attribute->nodeName] ?? null;

            if ($pattern && preg_match($pattern, trim($attribute->value))) {
                $element->setAttribute($attribute->nodeName, trim($attribute->value));
            } else {
                $dropped[] = $attribute->nodeName;
            }
        }

        // only a group has children; anything inside a shape (an <animate>, a <script>, …) goes
        foreach ($node->childNodes as $child) {
            if ($name === 'g') {
                if ($clean = self::copy($child, $target, $dropped)) {
                    $element->appendChild($clean);
                }
            } elseif ($child instanceof DOMElement || trim($child->textContent) !== '') {
                $dropped[] = $child instanceof DOMElement ? '<'.$child->nodeName.'>' : 'text';
            }
        }

        return $element;
    }
}
