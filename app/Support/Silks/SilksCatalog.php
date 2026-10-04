<?php

namespace App\Support\Silks;

use App\Models\SilkColor;
use App\Models\SilkPattern;

/**
 * What the silks designer offers right now: the active colours and each area's active patterns,
 * named in the current locale. "plain" is always offered and has no row.
 */
final class SilksCatalog
{
    public const PLAIN = 'plain';

    /**
     * @param  array<string, array{key: string, name: string, hex: string}>  $colors
     * @param  array<string, array<string, array{key: string, name: string, svg: string}>>  $patterns  area => key => pattern
     */
    public function __construct(public readonly array $colors, public readonly array $patterns) {}

    public static function load(): self
    {
        $colors = SilkColor::query()->active()->get()
            ->mapWithKeys(fn (SilkColor $color) => [$color->key => ['key' => $color->key, 'name' => $color->localizedName(), 'hex' => $color->hex]])
            ->all();

        // the designer needs two colours to show anything: switching every colour off brings the defaults back
        if (count($colors) < 2) {
            $colors = collect(SilksDefaults::colors())
                ->mapWithKeys(fn (array $color) => [$color['key'] => ['key' => $color['key'], 'name' => app()->getLocale() === 'ar' ? $color['ar_name'] : $color['en_name'], 'hex' => $color['hex']]])
                ->all();
        }

        $patterns = array_fill_keys(SilksTemplate::AREAS, []);

        foreach (SilkPattern::query()->active()->whereIn('area', SilksTemplate::AREAS)->get() as $pattern) {
            if ($pattern->svg !== '' && $pattern->key !== self::PLAIN) {
                $patterns[$pattern->area][$pattern->key] = ['key' => $pattern->key, 'name' => $pattern->localizedName(), 'svg' => $pattern->svg];
            }
        }

        return new self($colors, $patterns);
    }

    /** The same catalog with one area's pattern added or replaced (the admin preview of an unsaved pattern). */
    public function withPattern(string $area, string $key, string $name, string $svg): self
    {
        $patterns = $this->patterns;
        $patterns[$area][$key] = ['key' => $key, 'name' => $name, 'svg' => $svg];

        return new self($this->colors, $patterns);
    }

    public function hasPattern(string $area, ?string $key): bool
    {
        return $key === self::PLAIN || isset($this->patterns[$area][$key]);
    }

    /** Dark or white: whichever reads better on top of the given colour (the tick on a swatch). */
    public static function ink(string $hex): string
    {
        [$r, $g, $b] = sscanf(str_pad(ltrim($hex, '#'), 6, '0'), '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#1c1917' : '#ffffff';
    }

    public function plainName(): string
    {
        return __('silks.plain');
    }

    /** Everything the designer's script needs, in one JSON-able array. */
    public function forScript(): array
    {
        return [
            'colors' => array_values($this->colors),
            'patterns' => array_map(fn (array $patterns) => array_values($patterns), $this->patterns),
            'plain' => $this->plainName(),
            'describe' => trans('silks.describe'),
        ];
    }
}
