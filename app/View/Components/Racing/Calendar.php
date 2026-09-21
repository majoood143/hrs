<?php

namespace App\View\Components\Racing;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The season calendar: a season picker and the twelve months (October to September) with the race days
 * as links. Used by the "Race Calendar" CMS block and by /racing/calendar, so it reads its own season
 * from `?season=25/26` and submits back to whatever page it sits on.
 */
class Calendar extends Component
{
    /** @var list<string> */
    public array $seasons = [];

    public ?string $season = null;

    /** @var list<array{label: string, weeks: list<list<?array{day: int, date: string, meeting: ?string}>>}> */
    public array $months = [];

    public int $raceDays = 0;

    public bool $unavailable = false;

    public string $action;

    public function __construct(RacingClient $racing, Request $request)
    {
        $this->action = $request->url();

        try {
            $this->seasons = $racing->seasons();

            $requested = $request->query('season');
            $this->season = is_string($requested) && in_array($requested, $this->seasons, true) ? $requested : ($this->seasons[0] ?? null);

            if ($this->season !== null) {
                $days = $racing->raceDays($this->season);

                $this->raceDays = count($days);
                $this->months = $this->months($this->season, $days);
            }
        } catch (RacingUnavailableException $e) {
            report($e);

            $this->unavailable = true;
        }
    }

    public function render(): View
    {
        return view('components.racing.calendar');
    }

    /**
     * @param  array<string, ?string>  $days  Y-m-d => meeting name
     */
    private function months(string $season, array $days): array
    {
        $start = CarbonImmutable::parse(RacingClient::seasonRange($season)['from']);
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $first = $start->addMonths($i);

            // weeks run Saturday to Friday, like the source's own calendar (Carbon: 0 = Sunday .. 6 = Saturday)
            $cells = array_fill(0, ($first->dayOfWeek + 1) % 7, null);

            for ($day = 1; $day <= $first->daysInMonth; $day++) {
                $date = $first->day($day)->format('Y-m-d');

                $cells[] = ['day' => $day, 'date' => $date, 'meeting' => $days[$date] ?? null, 'race' => array_key_exists($date, $days)];
            }

            $months[] = [
                'label' => $first->locale(app()->getLocale())->translatedFormat('F Y'),
                'weeks' => array_chunk(array_pad($cells, (int) (ceil(count($cells) / 7) * 7), null), 7),
            ];
        }

        return $months;
    }
}
