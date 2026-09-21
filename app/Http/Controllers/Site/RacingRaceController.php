<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Support\RacingSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The race-day pages: results, entries, race card and form guide. They all share one shape,
 * a meeting header, a tab per race, and the selected race's table.
 */
class RacingRaceController extends Controller
{
    public function __construct(private readonly RacingClient $racing)
    {
    }

    /**
     * @param  string  $page  results|entries|card|form-guide (route-constrained)
     * @param  ?int  $race  null = the source's default meeting for that page (latest results / next entries)
     */
    public function show(string $page, ?int $race = null): View
    {
        abort_unless(isset(RacingClient::RACE_PAGES[$page]), 404);

        $locale = app()->getLocale();

        try {
            // vet the id first: the source hangs on unknown ids for the card / form guide pages
            abort_if($race !== null && ! $this->racing->raceInfo($race)['exists'], 404);

            $meeting = $this->racing->meeting($page, $race, $locale);
            $selected = $race === null
                ? ($meeting['races'][0] ?? null)
                : collect($meeting['races'] ?? [])->firstWhere('id', $race);

            // an id the source knows but that is not in the meeting it returned: do not guess
            abort_if($race !== null && $selected === null, 404);

            $detail = $selected ? $this->racing->raceDetail($page, $selected['id'], $locale) : null;
        } catch (RacingUnavailableException $e) {
            report($e);

            return $this->render($page, $race, unavailable: true);
        }

        return $this->render($page, $race, $meeting, $selected, $detail);
    }

    /** /racing/race/{id}: send a bare race link to the page the source itself would open (results, or entries if it has not run yet). */
    public function race(int $race): RedirectResponse|View
    {
        try {
            $info = $this->racing->raceInfo($race);
        } catch (RacingUnavailableException $e) {
            report($e);

            return $this->render('results', $race, unavailable: true);
        }

        abort_unless($info['exists'], 404);

        $page = RacingClient::pageKey($info['page']);

        return redirect()->route('racing.meeting', ['page' => $page, 'race' => $race]);
    }

    private function render(string $page, ?int $race, ?array $meeting = null, ?array $selected = null, ?array $detail = null, bool $unavailable = false): View
    {
        return view('site.racing.meeting', [
            'page' => $page,
            'carry' => $race,
            'meeting' => $meeting,
            'selected' => $selected,
            'detail' => $detail,
            'unavailable' => $unavailable,
        ] + RacingSeo::for(
            __('racing.pages.' . $page . '.title') . ($selected['title'] ?? false ? ' — ' . $selected['title'] : ''),
            implode(' · ', array_filter([$meeting['heading'] ?? null, ...($selected['info'] ?? [])])),
        ));
    }
}
