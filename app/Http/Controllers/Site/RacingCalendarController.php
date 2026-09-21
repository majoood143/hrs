<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Support\RacingSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RacingCalendarController extends Controller
{
    public function __construct(private readonly RacingClient $racing)
    {
    }

    public function index(): View
    {
        return view('site.racing.calendar', ['notice' => null] + RacingSeo::for(__('racing.calendar.title')));
    }

    /**
     * A click on a calendar date: on to that meeting's results once it has run, its entries before that.
     * Only dates the calendar actually lists are looked up, so this cannot be used to hammer the source.
     */
    public function day(string $date): RedirectResponse|View
    {
        abort_unless(preg_match('/^20\d{2}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)), 404);

        try {
            $season = RacingClient::seasonOf($date);

            abort_unless(in_array($season, $this->racing->seasons(), true), 404);
            abort_unless(array_key_exists($date, $this->racing->raceDays($season)), 404);

            $link = $this->racing->raceLinkByDate($date);
        } catch (RacingUnavailableException $e) {
            report($e);

            return view('site.racing.calendar', ['notice' => __('racing.unavailable_title')] + RacingSeo::for(__('racing.calendar.title')));
        }

        abort_if($link === null, 404);

        return redirect()->route('racing.meeting', ['page' => $link['page'], 'race' => $link['race']]);
    }
}
