<?php

namespace App\Support\Silks;

use App\Models\SilkColor;
use App\Models\SilkPattern;

/**
 * The colours and patterns a fresh install starts with (the migration inserts them, and the admin
 * "Add missing defaults" actions restore any that were deleted). Colours are the 18 standard
 * racing colours; patterns are the usual registration patterns, drawn in SilksTemplate::BOXES.
 * "Plain" is never a row: every area can always be plain.
 */
final class SilksDefaults
{
    /** @return list<array{key: string, en_name: string, ar_name: string, hex: string}> */
    public static function colors(): array
    {
        return array_map(fn (array $row) => array_combine(['key', 'en_name', 'ar_name', 'hex'], $row), [
            ['white', 'White', 'أبيض', '#FFFFFF'],
            ['black', 'Black', 'أسود', '#141414'],
            ['red', 'Red', 'أحمر', '#D7262B'],
            ['maroon', 'Maroon', 'عنابي', '#6E1423'],
            ['pink', 'Pink', 'وردي', '#F4A6C0'],
            ['orange', 'Orange', 'برتقالي', '#F28C28'],
            ['yellow', 'Yellow', 'أصفر', '#F7D417'],
            ['beige', 'Beige', 'بيج', '#D9C49A'],
            ['brown', 'Brown', 'بني', '#6B3E1F'],
            ['light-green', 'Light Green', 'أخضر فاتح', '#9BD47E'],
            ['emerald-green', 'Emerald Green', 'أخضر زمردي', '#009B5A'],
            ['dark-green', 'Dark Green', 'أخضر داكن', '#0F4D2C'],
            ['light-blue', 'Light Blue', 'أزرق فاتح', '#8EC5EB'],
            ['royal-blue', 'Royal Blue', 'أزرق ملكي', '#1F4FBF'],
            ['dark-blue', 'Dark Blue', 'أزرق داكن', '#1B2A57'],
            ['mauve', 'Mauve', 'موف', '#B784A7'],
            ['purple', 'Purple', 'بنفسجي', '#5B2A86'],
            ['grey', 'Grey', 'رمادي', '#9AA0A6'],
        ]);
    }

    /** @return list<array{area: string, key: string, en_name: string, ar_name: string, svg: string}> */
    public static function patterns(): array
    {
        $rows = [];

        foreach (['body' => self::body(), 'sleeves' => self::sleeves(), 'cap' => self::cap()] as $area => $patterns) {
            foreach ($patterns as [$key, $en, $ar, $svg]) {
                $rows[] = ['area' => $area, 'key' => $key, 'en_name' => $en, 'ar_name' => $ar, 'svg' => $svg];
            }
        }

        return $rows;
    }

    /** Adds back the default colours that are missing (by key); returns how many were added. */
    public static function addMissingColors(): int
    {
        $existing = SilkColor::query()->pluck('key')->all();
        $next = (int) SilkColor::query()->max('sort');
        $added = 0;

        foreach (self::colors() as $color) {
            if (! in_array($color['key'], $existing, true)) {
                SilkColor::create($color + ['is_active' => true, 'sort' => ++$next]);
                $added++;
            }
        }

        return $added;
    }

    /** Adds back the default patterns that are missing (by area and key); returns how many were added. */
    public static function addMissingPatterns(): int
    {
        $existing = SilkPattern::query()->get(['area', 'key'])->map(fn (SilkPattern $p) => $p->area.'/'.$p->key)->all();
        $next = (int) SilkPattern::query()->max('sort');
        $added = 0;

        foreach (self::patterns() as $pattern) {
            if (! in_array($pattern['area'].'/'.$pattern['key'], $existing, true)) {
                SilkPattern::create($pattern + ['is_active' => true, 'sort' => ++$next]);
                $added++;
            }
        }

        return $added;
    }

    private static function body(): array
    {
        return [
            ['hoops', 'Hoops', 'أطواق', self::bands('y', [24, 72, 120, 168, 216], 24, 200)],
            ['hoop', 'Hoop', 'طوق', self::bands('y', [100], 40, 200)],
            ['stripes', 'Stripes', 'خطوط طولية', self::bands('x', [10, 50, 90, 130, 170], 20, 240)],
            ['stripe', 'Stripe', 'خط طولي', self::bands('x', [82], 36, 240)],
            ['braces', 'Braces', 'حمالات', self::bands('x', [50, 134], 16, 240)],
            ['halves', 'Halves', 'نصفان', '<rect x="100" y="-10" width="110" height="260"/>'],
            ['quartered', 'Quartered', 'أرباع', '<rect x="100" y="-10" width="110" height="130"/><rect x="-10" y="120" width="110" height="130"/>'],
            ['sash', 'Sash', 'وشاح', '<line x1="10" y1="0" x2="210" y2="230" stroke="currentColor" stroke-width="34"/>'],
            ['cross-belts', 'Cross Belts', 'أحزمة متقاطعة', '<line x1="10" y1="0" x2="210" y2="230" stroke="currentColor" stroke-width="26"/><line x1="190" y1="0" x2="-10" y2="230" stroke="currentColor" stroke-width="26"/>'],
            ['chevron', 'Chevron', 'شيفرون', self::chevrons([[80, 170]], 200, 32)],
            ['chevrons', 'Chevrons', 'شيفرونات', self::chevrons([[30, 110], [90, 170], [150, 230]], 200, 22)],
            ['inverted-chevron', 'Inverted Chevron', 'شيفرون مقلوب', self::chevrons([[200, 110]], 200, 32)],
            ['diagonal-stripes', 'Diagonal Stripes', 'خطوط مائلة', self::diagonals(200, 240, 40, 14)],
            ['diamond', 'Diamond', 'معين', self::diamond(100, 130, 52, 75)],
            ['diamonds', 'Diamonds', 'معينات', self::grid(200, 240, 40, 52, fn ($x, $y) => self::diamond($x, $y, 15, 21))],
            ['triple-diamond', 'Triple Diamond', 'ثلاثة معينات', self::diamond(100, 70, 26, 30).self::diamond(100, 130, 26, 30).self::diamond(100, 190, 26, 30)],
            ['spots', 'Spots', 'نقاط', self::grid(200, 240, 40, 40, fn ($x, $y) => self::circle($x, $y, 9))],
            ['large-spots', 'Large Spots', 'نقاط كبيرة', self::grid(200, 240, 76, 70, fn ($x, $y) => self::circle($x, $y, 19))],
            ['stars', 'Stars', 'نجوم', self::grid(200, 240, 44, 44, fn ($x, $y) => self::star($x, $y, 12))],
            ['star', 'Star', 'نجمة', self::star(100, 132, 50)],
            ['disc', 'Disc', 'قرص', self::circle(100, 130, 52)],
            ['check', 'Check', 'مربعات', self::checks(200, 240, 40)],
            ['diabolo', 'Diabolo', 'ساعة رملية', '<polygon points="14,16 186,16 108,128 180,240 20,240 92,128"/>'],
            ['inverted-triangle', 'Inverted Triangle', 'مثلث مقلوب', '<polygon points="10,10 190,10 100,170"/>'],
            ['hollow-box', 'Hollow Box', 'مربع مفرغ', '<path fill-rule="evenodd" d="M46 60 H154 V204 H46 Z M66 80 V184 H134 V80 Z"/>'],
            ['epaulettes', 'Epaulettes', 'كتافات', '<polygon points="8,16 68,-6 76,30 14,52"/><polygon points="192,16 132,-6 124,30 186,52"/>'],
            ['seams', 'Seams', 'خطوط الدرزات', '<polyline fill="none" stroke="currentColor" stroke-width="16" points="70,0 14,20 17,92 20,240"/><polyline fill="none" stroke="currentColor" stroke-width="16" points="130,0 186,20 183,92 180,240"/>'],
            ['horseshoe', 'Horseshoe', 'حدوة حصان', '<path fill="none" stroke="currentColor" stroke-width="18" stroke-linecap="square" d="M62 92 V148 A38 38 0 0 0 138 148 V92"/>'],
        ];
    }

    private static function sleeves(): array
    {
        return [
            ['hooped', 'Hoops', 'أطواق', self::bands('y', [14, 46, 78, 110], 16, 60)],
            ['armlet', 'Armlet', 'سوار', self::bands('y', [48], 20, 60)],
            ['striped', 'Stripes', 'خطوط طولية', self::bands('x', [9, 25, 41], 10, 135)],
            ['halved', 'Halves', 'نصفان', '<rect x="30" y="-10" width="40" height="155"/>'],
            ['chevrons', 'Chevrons', 'شيفرونات', self::chevrons([[10, 40], [50, 80], [90, 120]], 60, 10)],
            ['diamonds', 'Diamonds', 'معينات', self::grid(60, 135, 30, 34, fn ($x, $y) => self::diamond($x, $y, 10, 14))],
            ['spots', 'Spots', 'نقاط', self::grid(60, 135, 24, 24, fn ($x, $y) => self::circle($x, $y, 6))],
            ['stars', 'Stars', 'نجوم', self::grid(60, 135, 30, 30, fn ($x, $y) => self::star($x, $y, 9))],
            ['check', 'Check', 'مربعات', self::checks(60, 135, 20, 1)],
            ['seams', 'Seams', 'خط وسطي', self::bands('x', [26], 8, 135)],
            ['cuffs', 'Cuffs', 'أطراف الأكمام', '<rect x="-10" y="110" width="80" height="35"/>'],
        ];
    }

    private static function cap(): array
    {
        return [
            ['hooped', 'Hoops', 'أطواق', self::bands('y', [12, 36, 60], 12, 140)],
            ['hoop', 'Hoop', 'طوق', self::bands('y', [38], 16, 140)],
            ['striped', 'Stripes', 'خطوط طولية', self::bands('x', [6, 38, 62, 86, 118], 16, 72)],
            ['quartered', 'Quarters', 'أرباع', '<rect x="70" y="-10" width="80" height="50"/><rect x="-10" y="40" width="80" height="45"/>'],
            ['halved', 'Halves', 'نصفان', '<rect x="70" y="-10" width="80" height="95"/>'],
            ['star', 'Star', 'نجمة', self::star(70, 44, 21)],
            ['stars', 'Stars', 'نجوم', self::grid(140, 72, 30, 26, fn ($x, $y) => self::star($x, $y, 8))],
            ['spots', 'Spots', 'نقاط', self::grid(140, 72, 24, 22, fn ($x, $y) => self::circle($x, $y, 5))],
            ['diamond', 'Diamond', 'معين', self::diamond(70, 44, 18, 24)],
            ['diamonds', 'Diamonds', 'معينات', self::grid(140, 72, 26, 26, fn ($x, $y) => self::diamond($x, $y, 9, 12))],
            ['check', 'Check', 'مربعات', self::checks(140, 72, 18)],
        ];
    }

    /** Parallel bands across the area: horizontal (axis y) or vertical (axis x). */
    private static function bands(string $axis, array $starts, float $size, float $length): string
    {
        return implode('', array_map(fn ($at) => $axis === 'y'
            ? sprintf('<rect x="-10" y="%s" width="%s" height="%s"/>', $at, $length + 20, $size)
            : sprintf('<rect x="%s" y="-10" width="%s" height="%s"/>', $at, $size, $length + 20), $starts));
    }

    /** V shapes across the width: each [y at the sides, y at the middle]. */
    private static function chevrons(array $levels, float $width, float $stroke): string
    {
        return implode('', array_map(fn ($level) => sprintf(
            '<polyline fill="none" stroke="currentColor" stroke-width="%s" points="%s,%s %s,%s %s,%s"/>',
            $stroke, -10, $level[0] - ($level[1] - $level[0]) * 10 / ($width / 2), $width / 2, $level[1],
            $width + 10, $level[0] - ($level[1] - $level[0]) * 10 / ($width / 2),
        ), $levels));
    }

    private static function diagonals(float $width, float $height, float $step, float $stroke): string
    {
        $lines = '';

        for ($x = -$height; $x <= $width; $x += $step) {
            $lines .= sprintf('<line x1="%s" y1="-10" x2="%s" y2="%s" stroke="currentColor" stroke-width="%s"/>', $x - 10, $x + $height + 10, $height + 10, $stroke);
        }

        return $lines;
    }

    /** Shapes on a staggered grid centred on the area (every other row shifted half a step). */
    private static function grid(float $width, float $height, float $stepX, float $stepY, callable $shape): string
    {
        $out = '';
        $rows = (int) ceil($height / $stepY) + 1;
        $top = ($height - ($rows - 1) * $stepY) / 2;

        for ($row = 0; $row < $rows; $row++) {
            $shift = $row % 2 ? $stepX / 2 : 0;
            $cols = (int) ceil($width / $stepX) + 2;
            $left = $width / 2 - floor($cols / 2) * $stepX + $shift;

            for ($col = 0; $col <= $cols; $col++) {
                $out .= $shape(round($left + $col * $stepX, 1), round($top + $row * $stepY, 1));
            }
        }

        return $out;
    }

    private static function checks(float $width, float $height, float $size, int $parity = 0): string
    {
        $out = '';
        $left = $width / 2 - ceil($width / 2 / $size) * $size;

        for ($y = 0, $row = 0; $y < $height; $y += $size, $row++) {
            for ($x = $left, $col = 0; $x < $width; $x += $size, $col++) {
                if (($row + $col + $parity) % 2 === 0) {
                    $out .= sprintf('<rect x="%s" y="%s" width="%s" height="%s"/>', round($x, 1), $y, $size, $size);
                }
            }
        }

        return $out;
    }

    private static function diamond(float $cx, float $cy, float $halfWidth, float $halfHeight): string
    {
        return sprintf('<polygon points="%s,%s %s,%s %s,%s %s,%s"/>', $cx, $cy - $halfHeight, $cx + $halfWidth, $cy, $cx, $cy + $halfHeight, $cx - $halfWidth, $cy);
    }

    private static function circle(float $cx, float $cy, float $r): string
    {
        return sprintf('<circle cx="%s" cy="%s" r="%s"/>', $cx, $cy, $r);
    }

    /** A five-pointed star, point up. */
    private static function star(float $cx, float $cy, float $outer): string
    {
        $points = [];

        for ($i = 0; $i < 10; $i++) {
            $radius = $i % 2 ? $outer * 0.4 : $outer;
            $angle = deg2rad(-90 + $i * 36);
            $points[] = round($cx + $radius * cos($angle), 1).','.round($cy + $radius * sin($angle), 1);
        }

        return '<polygon points="'.implode(' ', $points).'"/>';
    }
}
