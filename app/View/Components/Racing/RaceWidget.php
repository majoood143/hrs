<?php

namespace App\View\Components\Racing;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The compact "Races" widget: one month with the race days highlighted, and the races of the selected
 * day underneath (time, distance, and links to its video / entries / race card / results).
 *
 * It is server-rendered and stateless: the month and day live in `?race_month=2026-10&race_date=2026-10-17`,
 * so every link works without JavaScript and a day can be shared. With no date chosen it opens on the
 * next race day (or the latest one once the season is over), like the source's own widget.
 */
class RaceWidget extends Component
{
    public const MONTH_KEY = 'race_month';

    public const DATE_KEY = 'race_date';

    /** Only sent to the fragment route: the path of the page the widget is on. */
    public const FROM_KEY = 'from';

    public bool $unavailable = false;

    /** Selected race day, Y-m-d. */
    public ?string $date = null;

    public string $today;

    /** Displayed month, Y-m. */
    public string $month;

    public string $monthLabel = '';

    public ?string $prevMonth = null;

    public ?string $nextMonth = null;

    /** @var list<list<?array{day: int, date: string, race: bool, meeting: ?string, selected: bool, today: bool}>> */
    public array $weeks = [];

    public int $raceDays = 0;

    /** @var list<array<string, mixed>> see RacingCalendarParser::parseRaces() */
    public array $races = [];

    public bool $racesUnavailable = false;

    public ?string $meeting = null;

    public ?string $meetingTime = null;

    /** @var array<string, mixed> the current query string minus our own keys, carried on every link */
    private array $carry;

    private string $action;

    /** @var list<string> */
    private array $seasons = [];

    /** @var array<string, array<string, ?string>> season => race days */
    private array $daysBySeason = [];

    public function __construct(private readonly RacingClient $racing, Request $request)
    {
        // asked for over the fragment route, the links must still point at the page the widget sits on
        $fragment = $request->routeIs('racing.widget');

        $this->action = $fragment ? url($this->hostPath($request->query(self::FROM_KEY))) : $request->url();
        $this->carry = $request->except($fragment ? [self::MONTH_KEY, self::DATE_KEY, self::FROM_KEY] : [self::MONTH_KEY, self::DATE_KEY]);
        $this->today = now()->format('Y-m-d');
        $this->month = substr($this->today, 0, 7);

        try {
            $this->seasons = $racing->seasons();

            $this->date = $this->raceDay($request->query(self::DATE_KEY)) ?? $this->defaultDay();
            $this->month = $this->month($request->query(self::MONTH_KEY)) ?? ($this->date !== null ? substr($this->date, 0, 7) : $this->month);

            $this->buildMonth();
        } catch (RacingUnavailableException $e) {
            report($e);

            $this->unavailable = true;

            return;
        }

        if ($this->date !== null) {
            try {
                $this->races = $racing->raceDay($this->date, app()->getLocale());
                $this->meeting = $this->races[0]['meeting'] ?? null;
                $this->meetingTime = $this->races[0]['meeting_time'] ?? null;
            } catch (RacingUnavailableException $e) {
                report($e);

                $this->racesUnavailable = true;
            }
        }
    }

    public function render(): View
    {
        return view('components.racing.race-widget');
    }

    /** Link to a month (and the day that stays selected) on the page the widget sits on. */
    public function url(string $month, ?string $date): string
    {
        $query = http_build_query($this->carry + array_filter([self::MONTH_KEY => $month, self::DATE_KEY => $date]));

        return $this->action . '?' . $query . '#race-widget';
    }

    /** A same-site path and nothing else, so the links can never be pointed at another host. */
    private function hostPath(mixed $value): string
    {
        return is_string($value) && preg_match('#^/(?!/)[\w\-./%]*$#', $value) === 1 ? $value : '/';
    }

    public function dateLabel(string $date): string
    {
        return CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('l j F Y');
    }

    /** @return array<string, ?string> Y-m-d => meeting name, for the season a date falls in (empty when we do not know that season) */
    private function daysFor(string $date): array
    {
        $season = RacingClient::seasonOf($date);

        if (! in_array($season, $this->seasons, true)) {
            return [];
        }

        return $this->daysBySeason[$season] ??= $this->racing->raceDays($season);
    }

    /** A requested day, only if it really is a race day: nothing else is ever looked up at the source. */
    private function raceDay(mixed $value): ?string
    {
        if (! is_string($value) || ! $this->isDate($value)) {
            return null;
        }

        return array_key_exists($value, $this->daysFor($value)) ? $value : null;
    }

    private function month(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return null;
        }

        return in_array(RacingClient::seasonOf("{$value}-01"), $this->seasons, true) ? $value : null;
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** The next race day from today; once the season is over, the first of the next season, else the last one run. */
    private function defaultDay(): ?string
    {
        $current = $this->daysFor($this->today);

        foreach (array_keys($current) as $day) {
            if ($day >= $this->today) {
                return $day;
            }
        }

        // ksort()ed by the parser, so the first key is the earliest
        $next = $this->daysFor(CarbonImmutable::parse(RacingClient::seasonRange(RacingClient::seasonOf($this->today))['to'])->addDay()->format('Y-m-d'));

        if ($next !== []) {
            return array_key_first($next);
        }

        // today's season is not offered (yet): show the newest one's last race day rather than an empty widget
        $latest = $current !== [] ? $current : $this->daysFor(RacingClient::seasonRange($this->seasons[0] ?? '')['from'] ?? '');

        return array_key_last($latest);
    }

    private function buildMonth(): void
    {
        $first = CarbonImmutable::parse("{$this->month}-01");
        $days = $this->daysFor($first->format('Y-m-d'));

        $this->monthLabel = $first->locale(app()->getLocale())->translatedFormat('F Y');
        $this->raceDays = count(array_filter(array_keys($days), fn (string $d) => str_starts_with($d, $this->month)));

        foreach (['prevMonth' => $first->subMonth(), 'nextMonth' => $first->addMonth()] as $property => $neighbour) {
            $this->{$property} = $this->month($neighbour->format('Y-m'));
        }

        // weeks run Saturday to Friday, like the source's own calendar (Carbon: 0 = Sunday .. 6 = Saturday)
        $cells = array_fill(0, ($first->dayOfWeek + 1) % 7, null);

        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = $first->day($day)->format('Y-m-d');

            $cells[] = [
                'day' => $day,
                'date' => $date,
                'race' => array_key_exists($date, $days),
                'meeting' => $days[$date] ?? null,
                'selected' => $date === $this->date,
                'today' => $date === $this->today,
            ];
        }

        $this->weeks = array_chunk(array_pad($cells, (int) (ceil(count($cells) / 7) * 7), null), 7);
    }
}
