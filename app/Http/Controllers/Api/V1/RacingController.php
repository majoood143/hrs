<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Racing\RacingHandicapHorseResource;
use App\Http\Resources\V1\Racing\RacingMeetingResource;
use App\Http\Resources\V1\Racing\RacingProfileResource;
use App\Http\Resources\V1\Racing\RacingRaceDayResource;
use App\Http\Resources\V1\Racing\RacingSearchGroupResource;
use App\Services\Racing\ArabicNameSearch;
use App\Services\Racing\HandicapRatings;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingSearchType;
use App\Services\Racing\RacingUnavailableException;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * The racing database (the website's /racing pages), read through the same RacingClient and the
 * same checks: race ids are vetted with the cheap raceInfo call and only dates the calendar lists
 * are looked up, because the source hangs on anything else. Nothing is stored; the source's answers
 * are cached (config/racing.php). When the source is down: 503 with `code: racing_unavailable`.
 */
class RacingController extends Controller
{
    private const SEARCH_TYPES = ['all', 'horse', 'owner', 'jockey', 'trainer'];

    public function __construct(
        private readonly RacingClient $racing,
        private readonly ArabicNameSearch $arabic,
    ) {}

    /**
     * Search the racing database
     *
     * Horses, owners, jockeys and trainers by name. `type=all` (default) gives the first results of
     * each group with its `total`; one type gives that group's results page by page (`meta`).
     * An Arabic name is matched in the source's Arabic lists (the source's search is Latin-only), so
     * its rows show what those lists know: a horse's rating and last run, a person's wins and runs.
     *
     * @unauthenticated
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:'.config('racing.min_query_length'), 'max:'.config('racing.max_query_length')],
            'type' => ['nullable', Rule::in(self::SEARCH_TYPES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = trim(preg_replace('/\s+/u', ' ', $validated['q']) ?? '');
        $type = ($validated['type'] ?? 'all') === 'all' ? RacingSearchType::All : RacingSearchType::fromGroup($validated['type']);
        $groups = $this->available(fn () => ArabicNameSearch::applies($query)
            ? $this->arabic->search($query, $type, app()->getLocale())
            : $this->racing->search($query, $type, app()->getLocale()));

        $sections = [];
        $meta = null;

        foreach (['horse', 'owner', 'jockey', 'trainer'] as $group) {
            if (($type->group() !== null && $type->group() !== $group) || ! isset($groups[$group])) {
                continue;
            }

            $rows = $groups[$group]['rows'];
            $total = count($rows);

            if ($type === RacingSearchType::All) {
                $rows = array_slice($rows, 0, config('racing.all_preview'));
            } else {
                $perPage = config('racing.per_page');
                $page = (int) ($validated['page'] ?? 1);
                $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
                $meta = ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)), 'per_page' => $perPage, 'total' => $total];
            }

            $sections[] = ['group' => $group, 'headers' => $groups[$group]['headers'], 'rows' => $rows, 'total' => $total];
        }

        if ($type !== RacingSearchType::All && $meta === null) {
            $meta = ['current_page' => 1, 'last_page' => 1, 'per_page' => config('racing.per_page'), 'total' => 0];
        }

        return RacingSearchGroupResource::collection($sections)
            ->additional(['meta' => ['query' => $query] + ($meta ?? [])])
            ->response();
    }

    /**
     * A racing profile
     *
     * A horse, owner, jockey or trainer: header facts, rating, and the source's tabs of tables (race
     * record, entries, statistics...). 404 for an unknown id.
     *
     * @param  'horse'|'owner'|'jockey'|'trainer'  $entity
     *
     * @unauthenticated
     */
    public function profile(string $entity, int $id): RacingProfileResource
    {
        abort_unless(isset(RacingClient::ENTITIES[$entity]), 404);

        $profile = $this->available(fn () => $this->racing->profile($entity, $id, app()->getLocale()));

        abort_if($profile === null, 404);

        return new RacingProfileResource($profile + ['entity' => $entity, 'id' => $id]);
    }

    /**
     * Race calendar
     *
     * The seasons (newest first) and every race day of one season (`season=25/26`, default the
     * newest), plus `next_day`: the race day to open on (the next one, else the latest run).
     *
     * @unauthenticated
     */
    public function calendar(Request $request): JsonResponse
    {
        return $this->available(function () use ($request) {
            $seasons = $this->racing->seasons();
            $requested = $request->query('season');
            $season = is_string($requested) && in_array($requested, $seasons, true) ? $requested : ($seasons[0] ?? null);

            abort_if($season === null, 404);

            $days = $this->racing->raceDays($season);

            return response()->json(['data' => [
                /** @var list<string> */
                'seasons' => $seasons,
                'season' => $season,
                /** @var list<array{date: string, meeting: ?string}> */
                'days' => array_map(fn (string $date, ?string $meeting) => ['date' => $date, 'meeting' => $meeting], array_keys($days), array_values($days)),
                'today' => now()->format('Y-m-d'),
                'next_day' => $this->racing->defaultRaceDay(now()->format('Y-m-d')),
                'web_url' => route('racing.calendar', ['season' => $season]),
            ]]);
        });
    }

    /**
     * Races on a race day
     *
     * The races of one day the calendar lists (`Y-m-d`), with which pages each one has yet. 404 for
     * any other date.
     *
     * @unauthenticated
     */
    public function day(string $date): RacingRaceDayResource
    {
        abort_unless(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]), 404);

        return $this->available(function () use ($date) {
            abort_unless(array_key_exists($date, $this->racing->raceDaysOfSeasonOf($date)), 404);

            return new RacingRaceDayResource(['date' => $date, 'races' => $this->racing->raceDay($date, app()->getLocale())]);
        });
    }

    /**
     * A race day page
     *
     * Results, entries, race card or form guide of the meeting a race belongs to, with that race's
     * detail. Without `race`, the source's default meeting for the page (latest results, next
     * entries). 404 for an unknown race.
     *
     * @param  'results'|'entries'|'card'|'form-guide'  $page
     *
     * @unauthenticated
     */
    public function meeting(string $page, ?int $race = null): RacingMeetingResource
    {
        abort_unless(isset(RacingClient::RACE_PAGES[$page]), 404);

        return $this->available(function () use ($page, $race) {
            $locale = app()->getLocale();

            // vet the id first: the source hangs on unknown ids for the card / form guide pages
            abort_if($race !== null && ! $this->racing->raceInfo($race)['exists'], 404);

            $meeting = $this->racing->meeting($page, $race, $locale);

            abort_if($meeting === null, 404);

            $selected = $race === null
                ? ($meeting['races'][0] ?? null)
                : collect($meeting['races'])->firstWhere('id', $race);

            // an id the source knows but that is not in the meeting it returned: do not guess
            abort_if($race !== null && $selected === null, 404);

            return new RacingMeetingResource([
                'page' => $page,
                'meeting' => $meeting,
                'selected' => $selected,
                'detail' => $selected ? $this->racing->raceDetail($page, $selected['id'], $locale) : null,
            ]);
        });
    }

    /**
     * Handicap ratings
     *
     * Every rated horse, filtered like the website (`breed` all|tb|pa, `location`
     * all|local|non-local, `q` part of the name, in any language) and sorted (`sort` name|rating|rating_date|last_ran,
     * `dir` asc|desc; unrated horses last). 50 per page. A cold list can take the source up to 45 s.
     *
     * @unauthenticated
     */
    public function handicap(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'breed' => ['nullable', Rule::in(array_keys(HandicapRatings::BREEDS))],
            'location' => ['nullable', Rule::in(array_keys(HandicapRatings::LOCATIONS))],
            'q' => ['nullable', 'string', 'max:'.config('racing.max_query_length')],
            'sort' => ['nullable', Rule::in(HandicapRatings::SORTS)],
            'dir' => ['nullable', Rule::in(HandicapRatings::DIRECTIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $breed = $validated['breed'] ?? 'all';
        $location = $validated['location'] ?? 'all';

        $q = trim((string) ($validated['q'] ?? ''));

        $horses = HandicapRatings::filterAndSort(
            $this->available(fn () => $this->racing->handicap(HandicapRatings::BREEDS[$breed], HandicapRatings::LOCATIONS[$location], app()->getLocale())),
            $q,
            $validated['sort'] ?? 'name',
            $validated['dir'] ?? 'asc',
            $q === '' ? [] : HandicapRatings::otherNames($this->racing, app()->getLocale()),
        );

        $perPage = config('racing.handicap_per_page');
        $page = (int) ($validated['page'] ?? 1);

        return RacingHandicapHorseResource::collection(new LengthAwarePaginator(
            array_slice($horses, ($page - 1) * $perPage, $perPage),
            count($horses),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')],
        ));
    }

    /**
     * Runs a source call; a source outage becomes a 503 the app can show with a retry.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function available(Closure $call): mixed
    {
        try {
            return $call();
        } catch (RacingUnavailableException $e) {
            report($e);

            throw new HttpResponseException(response()->json([
                'message' => __('racing.unavailable_title'),
                'code' => 'racing_unavailable',
            ], 503));
        }
    }
}
