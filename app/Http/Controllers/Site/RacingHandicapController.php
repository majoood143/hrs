<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\HandicapRatings;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Support\RacingSeo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class RacingHandicapController extends Controller
{
    public function __construct(private readonly RacingClient $racing) {}

    public function index(Request $request): View
    {
        $breed = $this->choice($request->query('breed'), array_keys(HandicapRatings::BREEDS), 'all');
        $location = $this->choice($request->query('location'), array_keys(HandicapRatings::LOCATIONS), 'all');
        $sort = $this->choice($request->query('sort'), HandicapRatings::SORTS, 'name');
        $dir = $this->choice($request->query('dir'), HandicapRatings::DIRECTIONS, 'asc');
        $raw = $request->query('q');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, config('racing.max_query_length')) : '';

        $state = ['breed' => $breed, 'location' => $location, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'horses' => null, 'unavailable' => false];

        try {
            $horses = $this->racing->handicap(HandicapRatings::BREEDS[$breed], HandicapRatings::LOCATIONS[$location], app()->getLocale());
        } catch (RacingUnavailableException $e) {
            report($e);

            return view('site.racing.handicap', ['unavailable' => true] + $state + RacingSeo::for(__('racing.pages.handicap.title')));
        }

        $horses = HandicapRatings::filterAndSort($horses, $q, $sort, $dir, $q === '' ? [] : HandicapRatings::otherNames($this->racing, app()->getLocale()));

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
}
