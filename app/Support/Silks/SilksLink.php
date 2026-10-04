<?php

namespace App\Support\Silks;

use Mpdf\QrCode\QrCode;
use Throwable;

/**
 * The address that reopens a design: the designer page it was made on, plus the language and the
 * design in its short form (SilksDesign::compact()). Printed and encoded as a QR code on the
 * downloads. The page comes from the visitor, so only a page on this site is used.
 */
final class SilksLink
{
    public static function for(mixed $page, SilksDesign $design, ?string $locale = null): ?string
    {
        if (! is_string($page) || strlen($page) > 2000) {
            return null;
        }

        $parts = parse_url($page);

        if (! $parts || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || ($parts['host'] ?? null) !== request()->getHost()) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin.($parts['path'] ?? '/').'?'.http_build_query(['lang' => $locale ?? app()->getLocale(), 'silks' => $design->compact()], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The QR code of $url as SVG rectangles in a $size × $size box at the origin (no quiet zone:
     * the caller leaves white space around it), or '' if the text is too long for a QR code.
     */
    public static function qrRects(string $url, float $size): string
    {
        try {
            $qr = new QrCode($url, 'M');
        } catch (Throwable) {
            return '';
        }

        $qr->disableBorder();
        $modules = $qr->getQrSize() - 8; // getQrSize() counts the 4-module border on each side
        $final = $qr->getFinal();
        $cell = $size / $modules;
        $rects = '';

        for ($row = 0; $row < $modules; $row++) {
            $start = null;

            for ($col = 0; $col <= $modules; $col++) {
                $dark = $col < $modules && $final[($col + 4) + ($row + 4) * $qr->getQrSize() + 1];

                if ($dark && $start === null) {
                    $start = $col;
                } elseif (! $dark && $start !== null) {
                    $rects .= sprintf('<rect x="%s" y="%s" width="%s" height="%s"/>', round($start * $cell, 2), round($row * $cell, 2), round(($col - $start) * $cell, 2), round($cell, 2));
                    $start = null;
                }
            }
        }

        return $rects;
    }
}
