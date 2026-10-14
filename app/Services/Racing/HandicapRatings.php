<?php

namespace App\Services\Racing;

/**
 * The handicap ratings list as the website and the app show it: the filter values in URLs, the
 * name filter and the sort (unrated / never-run horses always last, whichever direction).
 */
final class HandicapRatings
{
    /** URL value => value the source expects. */
    public const BREEDS = ['all' => '', 'tb' => 'TB', 'pa' => 'PA'];

    public const LOCATIONS = ['all' => '1', 'local' => '2', 'non-local' => '3'];

    public const SORTS = ['name', 'rating', 'rating_date', 'last_ran'];

    public const DIRECTIONS = ['asc', 'desc'];

    /**
     * @param  list<array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string}>  $horses
     * @return list<array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string}>
     */
    public static function filterAndSort(array $horses, string $q, string $sort, string $dir): array
    {
        if ($q !== '') {
            $horses = array_values(array_filter($horses, fn (array $h) => mb_stripos($h['name'], $q) !== false));
        }

        $key = fn (array $h) => $sort === 'name' ? mb_strtolower($h['name']) : $h[$sort === 'rating' ? 'rating' : $sort];

        usort($horses, function (array $a, array $b) use ($key, $dir) {
            [$x, $y] = [$key($a), $key($b)];

            if ($x === null || $y === null) {
                return $x === $y ? 0 : ($x === null ? 1 : -1);
            }

            $cmp = $x <=> $y;

            return ($dir === 'desc' ? -$cmp : $cmp) ?: strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
        });

        return $horses;
    }
}
