<?php

namespace App\Services\Forms;

use Illuminate\Support\Collection;

/**
 * Turns form insights into what the browser draws (`resources/js/form-charts.js`, through the
 * `<x-form-insights.chart>` component): a plain array, so the same chart renders in the admin and in
 * the public CMS block.
 *
 * Forms by the job of the data: a field's answers are a horizontal bar of one colour (the form's own
 * choice order, so ordered scales read in order); a donut only when the admin asks for one, the answers
 * are one-of-several and there are at most six; a tick box is a meter (a two-slice pie says less);
 * numbers, dates and the submissions over time are columns; nationalities are a map plus a top-ten bar.
 */
class FormChart
{
    public const STYLES = ['auto', 'bar', 'donut'];

    /** A donut past this many slices is unreadable (and runs out of safe colours): it becomes a bar. */
    public const MAX_DONUT_SLICES = 6;

    public const TOP_NATIONALITIES = 10;

    /**
     * @param  array<string, mixed>  $result  a FormInsights::fieldResult()
     * @return array<string, mixed>
     */
    public static function forField(array $result, string $style = 'auto', bool $percent = false, bool $map = false): array
    {
        $rows = $result['rows'];

        return match ($result['kind']) {
            'boolean' => self::make('meter', $rows, $result, $percent),
            'number', 'date' => self::make('column', $rows, $result, $percent, valueLabels: count($rows) <= 12),
            'nationality' => $map
                ? self::make('map', $rows, $result, $percent)
                : self::make('bar', self::top($rows, self::TOP_NATIONALITIES, $result['answered']), $result, $percent),
            default => $style === 'donut' && $result['kind'] === 'choices' && count($rows) <= self::MAX_DONUT_SLICES
                ? self::make('donut', $rows, $result, $percent)
                : self::make('bar', $rows, $result, $percent),
        };
    }

    /**
     * @param  Collection<string, array{label: string, count: int}>  $series  FormInsights::timeline()
     * @return array<string, mixed>
     */
    public static function timeline(Collection $series, string $title): array
    {
        $total = (int) $series->sum('count');
        $rows = $series->map(fn (array $b, string $key): array => ['key' => $key, 'label' => $b['label'], 'count' => $b['count'], 'share' => $total > 0 ? round($b['count'] / $total * 100, 1) : 0.0])->values()->all();

        return self::make('column', $rows, ['label' => $title, 'answered' => $total], false, valueLabels: count($rows) <= 12);
    }

    /**
     * @param  array<int, array{key: string, label: string, count: int}>  $rows
     * @return array<string, mixed>
     */
    public static function counts(array $rows, string $title): array
    {
        $total = (int) array_sum(array_column($rows, 'count'));
        $rows = array_map(fn (array $row): array => $row + ['share' => $total > 0 ? round($row['count'] / $total * 100, 1) : 0.0], $rows);

        return self::make('bar', $rows, ['label' => $title, 'answered' => $total], false);
    }

    /**
     * The first $limit rows, the rest folded into one "Other" row.
     *
     * @param  array<int, array<string, mixed>>  $rows  biggest first
     * @return array<int, array<string, mixed>>
     */
    public static function top(array $rows, int $limit, int $answered): array
    {
        // an "Other" row already there (rare answers folded by the public block) joins the new one
        $other = collect($rows)->firstWhere('key', '__other');
        $rows = array_values(array_filter($rows, fn (array $row) => $row['key'] !== '__other'));

        if (count($rows) <= $limit && $other === null) {
            return $rows;
        }

        $count = (int) array_sum(array_column(array_slice($rows, $limit), 'count')) + (int) ($other['count'] ?? 0);

        return [
            ...array_slice($rows, 0, $limit),
            self::otherRow($count, $answered),
        ];
    }

    /** @return array{key: string, label: string, count: int, share: float, code: null} */
    public static function otherRow(int $count, int $answered): array
    {
        return ['key' => '__other', 'label' => __('form_charts.other'), 'count' => $count, 'share' => $answered > 0 ? round($count / $answered * 100, 1) : 0.0, 'code' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function make(string $type, array $rows, array $result, bool $percent, bool $valueLabels = true): array
    {
        $rows = array_values($rows);
        $locale = app()->getLocale();

        return [
            'type' => $type,
            'title' => (string) ($result['label'] ?? ''),
            'labels' => array_map(fn (array $row): string => (string) $row['label'], $rows),
            'counts' => array_map(fn (array $row): int => (int) $row['count'], $rows),
            'shares' => array_map(fn (array $row): float => (float) ($row['share'] ?? 0), $rows),
            // the colour of a donut slice follows its answer (the choice's place in the form), never its rank
            'slots' => array_keys($rows),
            'codes' => $type === 'map' ? array_map(fn (array $row): ?string => $row['code'] ?? null, $rows) : [],
            'format' => $percent ? 'percent' : 'count',
            'answered' => (int) ($result['answered'] ?? 0),
            'valueLabels' => $valueLabels,
            'series' => __('form_charts.answers'),
            'locale' => $locale,
            'dir' => config("languages.available.{$locale}.dir", 'ltr'),
            'height' => match ($type) {
                'bar' => max(120, count($rows) * 34 + 48),
                'donut' => 300,
                'map' => 380,
                default => 280,
            },
        ];
    }
}
