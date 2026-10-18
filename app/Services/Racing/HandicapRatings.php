<?php

namespace App\Services\Racing;

/**
 * The handicap ratings list as the website and the app show it: the filter values in URLs, the
 * name filter and the sort (unrated / never-run horses always last, whichever direction).
 *
 * The source names the horses in the page's language (Arabic names in Arabic), so the name filter
 * also matches each horse's name in the other languages: "shiyam" finds "بي.أف . شيم" in Arabic.
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
     * @param  array<int, list<string>>  $otherNames  horse id => its names in the other languages ({@see otherNames()})
     * @return list<array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string}>
     */
    public static function filterAndSort(array $horses, string $q, string $sort, string $dir, array $otherNames = []): array
    {
        if ($q !== '') {
            $q = ArabicNameSearch::fold($q);

            $horses = array_values(array_filter($horses, function (array $h) use ($q, $otherNames) {
                foreach ([$h['name'], ...($otherNames[$h['id']] ?? [])] as $name) {
                    if (mb_strpos(ArabicNameSearch::fold($name), $q) !== false) {
                        return true;
                    }
                }

                return false;
            }));
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

    /**
     * Every horse's name in the languages other than $locale, for the name filter. Read from the
     * full list (all breeds and locations: horse ids are the same in every list, and that one is
     * kept warm by `racing:warm`); a language the source cannot give right now is skipped.
     *
     * @return array<int, list<string>>
     */
    public static function otherNames(RacingClient $racing, string $locale): array
    {
        $names = [];

        foreach (array_keys(config('languages.available')) as $other) {
            if ($other === $locale) {
                continue;
            }

            try {
                foreach ($racing->handicap(self::BREEDS['all'], self::LOCATIONS['all'], $other) as $horse) {
                    $names[$horse['id']][] = $horse['name'];
                }
            } catch (RacingUnavailableException $e) {
                report($e);
            }
        }

        return $names;
    }
}
