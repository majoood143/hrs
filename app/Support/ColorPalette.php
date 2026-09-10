<?php

namespace App\Support;

class ColorPalette
{
    /**
     * Tailwind-style shade scale expressed as a mix ratio toward white
     * (positive) or black (negative), relative to the given base color
     * which is treated as the 600 step.
     */
    private const STOPS = [
        50 => 0.95,
        100 => 0.90,
        200 => 0.75,
        300 => 0.60,
        400 => 0.35,
        500 => 0.15,
        600 => 0.0,
        700 => -0.15,
        800 => -0.30,
        900 => -0.45,
        950 => -0.60,
    ];

    /**
     * Generate a 50-950 shade scale from a single hex color.
     *
     * @return array<int, string>
     */
    public static function shades(string $hex): array
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        $shades = [];

        foreach (self::STOPS as $stop => $ratio) {
            $shades[$stop] = match (true) {
                $ratio === 0.0 => self::rgbToHex($r, $g, $b),
                $ratio > 0 => self::mix($r, $g, $b, 255, 255, 255, $ratio),
                default => self::mix($r, $g, $b, 0, 0, 0, abs($ratio)),
            };
        }

        return $shades;
    }

    private static function mix(int $r, int $g, int $b, int $tr, int $tg, int $tb, float $ratio): string
    {
        return self::rgbToHex(
            (int) round($r + ($tr - $r) * $ratio),
            (int) round($g + ($tg - $g) * $ratio),
            (int) round($b + ($tb - $b) * $ratio),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '888888';
        }

        return array_map('hexdec', str_split(substr($hex, 0, 6), 2));
    }

    private static function rgbToHex(int $r, int $g, int $b): string
    {
        $clamp = fn (int $v) => max(0, min(255, $v));

        return sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
    }
}
