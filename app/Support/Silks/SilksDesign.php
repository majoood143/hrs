<?php

namespace App\Support\Silks;

use Illuminate\Support\Str;

/**
 * One set of silks: for each area a pattern, a main colour and a pattern colour. It travels as a
 * plain query string (?body=hoops&body1=royal-blue&body2=white&sleeves=…&cap=…), which is the
 * share link, the no-JS form submission and the image URL at once. Anything unknown falls back
 * to a default, so an old link whose pattern or colour was removed still draws something.
 */
final class SilksDesign
{
    /** @var array<string, array{0: string, 1: string, 2: string}> area => [pattern, main colour, pattern colour] */
    public const DEFAULT = [
        'body' => ['hoops', 'royal-blue', 'white'],
        'sleeves' => [SilksCatalog::PLAIN, 'white', 'royal-blue'],
        'cap' => ['star', 'royal-blue', 'white'],
    ];

    /** @param array<string, array{pattern: string, base: string, accent: string}> $choice */
    private function __construct(public readonly SilksCatalog $catalog, public readonly array $choice) {}

    public static function fromQuery(array $query, SilksCatalog $catalog): self
    {
        $query = self::expandCompact($query);
        $colors = array_keys($catalog->colors);
        $choice = [];

        foreach (SilksTemplate::AREAS as $area) {
            [$pattern, $base, $accent] = self::DEFAULT[$area];

            $pattern = self::pick($query[$area] ?? null, fn ($key) => $catalog->hasPattern($area, $key))
                ?? ($catalog->hasPattern($area, $pattern) ? $pattern : SilksCatalog::PLAIN);
            $base = self::pick($query[$area.'1'] ?? null, fn ($key) => isset($catalog->colors[$key]))
                ?? (isset($catalog->colors[$base]) ? $base : $colors[0]);
            $accent = self::pick($query[$area.'2'] ?? null, fn ($key) => isset($catalog->colors[$key]))
                ?? (isset($catalog->colors[$accent]) && $accent !== $base ? $accent : current(array_diff($colors, [$base])));

            $choice[$area] = ['pattern' => $pattern, 'base' => $base, 'accent' => $accent];
        }

        return new self($catalog, $choice);
    }

    /**
     * The short form printed on the downloads and in their QR codes: ?silks=body~sleeves~cap, each
     * "pattern.main.pattern-colour" (keys are letters, digits, - and _, so . and ~ are free).
     * Spelled-out parameters, when also present, win.
     */
    public function compact(): string
    {
        return implode('~', array_map(fn (array $choice) => $choice['pattern'].'.'.$choice['base'].'.'.$choice['accent'], $this->choice));
    }

    private static function expandCompact(array $query): array
    {
        if (! is_string($query['silks'] ?? null)) {
            return $query;
        }

        foreach (array_slice(explode('~', $query['silks']), 0, count(SilksTemplate::AREAS)) as $i => $part) {
            $area = SilksTemplate::AREAS[$i];
            [$pattern, $base, $accent] = array_pad(explode('.', $part, 3), 3, null);
            $query += array_filter([$area => $pattern, $area.'1' => $base, $area.'2' => $accent], fn ($value) => $value !== null && $value !== '');
        }

        return $query;
    }

    private static function pick(mixed $value, callable $exists): ?string
    {
        return is_string($value) && $value !== '' && $exists($value) ? $value : null;
    }

    /** @return array<string, string> */
    public function query(): array
    {
        $query = [];

        foreach ($this->choice as $area => $choice) {
            $query[$area] = $choice['pattern'];
            $query[$area.'1'] = $choice['base'];
            $query[$area.'2'] = $choice['accent'];
        }

        return $query;
    }

    /**
     * Each area ready to draw.
     *
     * @return array<string, array{pattern: string, svg: string, base: string, accent: string}>
     */
    public function paint(): array
    {
        $paint = [];

        foreach ($this->choice as $area => $choice) {
            $paint[$area] = [
                'pattern' => $choice['pattern'],
                'svg' => $this->catalog->patterns[$area][$choice['pattern']]['svg'] ?? '',
                'base' => $this->catalog->colors[$choice['base']]['hex'],
                'accent' => $this->catalog->colors[$choice['accent']]['hex'],
            ];
        }

        return $paint;
    }

    /**
     * Each area spelled out, in the current locale (the PDF's table).
     *
     * @return array<string, array{pattern: string, base: array{name: string, hex: string}, accent: ?array{name: string, hex: string}}>
     */
    public function details(): array
    {
        $details = [];

        foreach ($this->choice as $area => $choice) {
            $plain = $choice['pattern'] === SilksCatalog::PLAIN;
            $base = $this->catalog->colors[$choice['base']];
            $accent = $this->catalog->colors[$choice['accent']];

            $details[$area] = [
                'pattern' => $plain ? $this->catalog->plainName() : $this->catalog->patterns[$area][$choice['pattern']]['name'],
                'base' => ['name' => $base['name'], 'hex' => $base['hex']],
                'accent' => $plain ? null : ['name' => $accent['name'], 'hex' => $accent['hex']],
            ];
        }

        return $details;
    }

    /** "Royal blue, white hoops, white sleeves, royal blue cap, white star", in the current locale. */
    public function describe(): string
    {
        $parts = [];

        foreach ($this->choice as $area => $choice) {
            $plain = $choice['pattern'] === SilksCatalog::PLAIN;

            $parts[] = __('silks.describe.'.$area.($plain ? '' : '_pattern'), [
                'base' => mb_strtolower($this->catalog->colors[$choice['base']]['name']),
                'colour' => mb_strtolower($this->catalog->colors[$choice['accent']]['name']),
                'pattern' => $plain ? '' : mb_strtolower($this->catalog->patterns[$area][$choice['pattern']]['name']),
            ]);
        }

        return Str::ucfirst(implode(__('silks.describe.separator'), $parts));
    }
}
