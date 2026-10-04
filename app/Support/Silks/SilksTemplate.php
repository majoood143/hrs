<?php

namespace App\Support\Silks;

/**
 * The flat jacket-and-cap drawing every design is painted on. Each area (body, sleeves, cap) is
 * drawn in its own local box, so a pattern is written once for its area and fits wherever the
 * area is placed: the sleeve pattern is reused (mirrored) for both sleeves.
 *
 * A pattern is plain SVG shapes in the area's local box (see BOXES); the area's shape clips it.
 */
final class SilksTemplate
{
    public const AREAS = ['body', 'sleeves', 'cap'];

    /** The whole figure, without a caption. */
    public const WIDTH = 400;

    public const HEIGHT = 400;

    /** Local box of each area: [width, height]. Admins draw patterns in these coordinates. */
    public const BOXES = [
        'body' => [200, 240],
        'sleeves' => [60, 135],
        'cap' => [140, 72],
    ];

    public const BODY = 'M70 0 Q100 22 130 0 L186 20 L183 92 L180 240 L20 240 L17 92 L14 20 Z';

    public const COLLAR = 'M70 0 Q100 22 130 0 L136 3 Q100 32 64 3 Z';

    public const SLEEVE = 'M0 0 L60 0 L52 135 L10 135 Z';

    public const CUFF = 'M9.2 124 L52.7 124';

    public const CAP_CROWN = 'M8 72 C8 22 36 2 70 2 C104 2 132 22 132 72 Z';

    public const CAP_PEAK = 'M8 72 L132 72 C138 72 140 80 132 84 C104 92 36 92 8 84 C0 80 2 72 8 72 Z';

    /** Where each area is placed in the figure. */
    public const PLACEMENT = [
        'body' => 'translate(100 140)',
        'sleeve_left' => 'translate(118 168) rotate(46)',
        'sleeve_right' => 'translate(400 0) scale(-1 1) translate(118 168) rotate(46)',
        'cap' => 'translate(130 26)',
    ];

    /** The shape that clips an area's pattern, in its local box. */
    public static function clipPath(string $area): string
    {
        return match ($area) {
            'body' => self::BODY,
            'sleeves' => self::SLEEVE,
            'cap' => self::CAP_CROWN,
        };
    }

    /** viewBox for a small preview of one area on its own. */
    public static function thumbViewBox(string $area): string
    {
        return match ($area) {
            'body' => '-6 -6 212 252',
            'sleeves' => '-12 -4 84 143',
            'cap' => '-4 -4 148 98',
        };
    }
}
