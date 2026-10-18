<?php

namespace App\Services\Racing;

use Illuminate\Support\Carbon;

/**
 * Racing search by an Arabic name.
 *
 * The source only searches names in Latin letters: an Arabic query finds nothing, whatever the
 * encoding or page language. So for an Arabic query we search the source's own lists in Arabic,
 * which carry the same ids as the profiles: horses in the handicap list (every rated horse), and
 * owners, jockeys and trainers in the Statistics page's all-seasons lists (everyone who has run a
 * horse), all cached and kept warm by `racing:warm`. Rows show what those lists know (a horse's
 * rating and last run, a person's wins and runs); the name opens the profile as usual. (Asking the
 * source for each result's own search row was tried: it answers one request at a time, ~1 s each,
 * so a page took 10-20 s, and under load it returns error pages.)
 */
final class ArabicNameSearch
{
    public function __construct(private readonly RacingClient $racing) {}

    public static function applies(string $query): bool
    {
        return preg_match('/\p{Arabic}/u', $query) === 1;
    }

    /**
     * A name as compared by the searches: lower case, Arabic without diacritics or tatweel and with
     * the letters people type interchangeably made one (أ إ آ ٱ → ا, ة → ه, ى → ي, ؤ → و, ئ → ي).
     */
    public static function fold(string $name): string
    {
        $name = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', mb_strtolower($name)) ?? $name;

        return strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    }

    /**
     * RacingClient::search()'s result for an Arabic query: a group per type asked for that has
     * matches, names starting with the query first, each name in the page's language.
     *
     * @return array<string, array{headers: list<string>, rows: list<array{id: int, cells: list<string>}>}>
     *
     * @throws RacingUnavailableException
     */
    public function search(string $query, RacingSearchType $type, string $locale): array
    {
        $groups = [];

        foreach (['horse', 'owner', 'jockey', 'trainer'] as $group) {
            if ($type->group() !== null && $type->group() !== $group) {
                continue;
            }

            $rows = $group === 'horse' ? $this->horses($query, $locale) : $this->people($group, $query, $locale);

            if ($rows !== []) {
                $groups[$group] = [
                    'headers' => $group === 'horse'
                        ? [__('racing.handicap.columns.horse'), __('racing.handicap.columns.rating'), __('racing.handicap.columns.last_ran')]
                        : [__("racing.profile_types.{$group}"), __('racing.stats.wins'), __('racing.stats.runs')],
                    'rows' => $rows,
                ];
            }
        }

        return $groups;
    }

    /**
     * The rated horses whose Arabic name contains the query: name, rating, last run.
     *
     * @return list<array{id: int, cells: list<string>}>
     *
     * @throws RacingUnavailableException
     */
    private function horses(string $query, string $locale): array
    {
        [$breed, $location] = [HandicapRatings::BREEDS['all'], HandicapRatings::LOCATIONS['all']];

        $horses = $this->matches(
            $query,
            $this->racing->handicap($breed, $location, 'ar'),
            fn () => $this->racing->handicap($breed, $location, $locale),
            $locale,
            fn (array $a, array $b) => $a['sort'] <=> $b['sort'],
        );

        return array_map(fn (array $horse) => ['id' => $horse['id'], 'cells' => [
            $horse['name'],
            (string) ($horse['rating'] ?? ($horse['label'] ?: '—')),
            $horse['last_ran'] ? Carbon::parse($horse['last_ran'])->locale($locale)->translatedFormat('j M Y') : '—',
        ]], $horses);
    }

    /**
     * The owners, jockeys or trainers whose Arabic name contains the query, the most successful
     * first after the names starting with it: name, wins, runs.
     *
     * @param  'owner'|'jockey'|'trainer'  $entity
     * @return list<array{id: int, cells: list<string>}>
     *
     * @throws RacingUnavailableException
     */
    private function people(string $entity, string $query, string $locale): array
    {
        $people = $this->matches(
            $query,
            $this->racing->people($entity, 'ar'),
            fn () => $this->racing->people($entity, $locale),
            $locale,
            fn (array $a, array $b) => $a['rank'] <=> $b['rank'],
        );

        return array_map(fn (array $person) => ['id' => $person['id'], 'cells' => [
            $person['name'],
            (string) $person['wins'],
            (string) $person['runs'],
        ]], $people);
    }

    /**
     * The entries of an Arabic list whose name contains the query, names starting with it first,
     * then in $order; on a page in another language, named from that language's list (same ids).
     *
     * @template T of array{id: int, name: string}
     *
     * @param  list<T>  $arabic
     * @param  callable(): list<T>  $translated  the list in $locale, only read when that is not Arabic
     * @param  callable(array, array): int  $order
     * @return list<T>
     */
    private function matches(string $query, array $arabic, callable $translated, string $locale, callable $order): array
    {
        $needle = self::fold($query);
        $names = $locale === 'ar' ? [] : array_column($translated(), 'name', 'id');
        $found = [];

        foreach ($arabic as $entry) {
            $folded = self::fold($entry['name']);
            $position = mb_strpos($folded, $needle);

            if ($position !== false) {
                $found[] = ['name' => $names[$entry['id']] ?? $entry['name'], 'starts' => $position === 0, 'sort' => $folded] + $entry;
            }
        }

        usort($found, fn (array $a, array $b) => ($b['starts'] <=> $a['starts']) ?: $order($a, $b));

        return array_map(fn (array $entry) => array_diff_key($entry, ['starts' => 0, 'sort' => 0]), $found);
    }
}
