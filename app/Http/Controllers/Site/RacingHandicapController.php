<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Support\RacingSeo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class RacingHandicapController extends Controller
{
    /** URL value => value the source expects. */
    private const BREEDS = ['all' => '', 'tb' => 'TB', 'pa' => 'PA'];

    private const LOCATIONS = ['all' => '1', 'local' => '2', 'non-local' => '3'];

    private const SORTS = ['name', 'rating', 'rating_date', 'last_ran'];

    public function __construct(private readonly RacingClient $racing)
    {
    }

    public function index(Request $request): View
    {
        $breed = $this->choice($request->query('breed'), array_keys(self::BREEDS), 'all');
        $location = $this->choice($request->query('location'), array_keys(self::LOCATIONS), 'all');
        $sort = $this->choice($request->query('sort'), self::SORTS, 'name');
        $dir = $this->choice($request->query('dir'), ['asc', 'desc'], 'asc');
        $raw = $request->query('q');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, config('racing.max_query_length')) : '';

        $state = ['breed' => $breed, 'location' => $location, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'horses' => null, 'unavailable' => false];

        try {
            $horses = $this->racing->handicap(self::BREEDS[$breed], self::LOCATIONS[$location], app()->getLocale());
        } catch (RacingUnavailableException $e) {
            report($e);

            return view('site.racing.handicap', ['unavailable' => true] + $state + RacingSeo::for(__('racing.pages.handicap.title')));
        }

        if ($q !== '') {
            $horses = array_values(array_filter($horses, fn (array $h) => mb_stripos($h['name'], $q) !== false));
        }

        $horses = $this->sorted($horses, $sort, $dir);

        $perPage = config('racing.handicap_per_page');
        $page = max(1, (int) $request->query('page', 1));

        $state['horses'] = new LengthAwarePaginator(
            array_slice($horses, ($page - 1) * $perPage, $perPage),
            count($horses),
            $perPage,
            $page,
            ['path' => route('racing.handicap'), 'query' => array_filter(compact('breed', 'location', 'q', 'sort', 'dir'), fn ($v) => $v !== '')],
        );

        return view('site.racing.handicap', $state + RacingSeo::for(__('racing.pages.handicap.title')));
    }

    /** A whitelisted query value; anything else (unknown value, array, ...) falls back to the default. */
    private function choice(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /** Unrated / never-run horses always sort last, whichever direction is chosen. */
    private function sorted(array $horses, string $sort, string $dir): array
    {
        $key = fn (array $h) => $sort === 'name' ? mb_strtolower($h['name']) : $h[$sort === 'rating' ? 'rating' : $sort];

        usort($horses, function (array $a, array $b) use ($key, $dir, $sort) {
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
