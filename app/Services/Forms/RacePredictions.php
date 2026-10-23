<?php

namespace App\Services\Forms;

use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Support\Carbon;
use Packstub\FormBuilder\Models\Form;

/**
 * Race prediction forms: the racing source's race days, races and runners turned into form
 * fields ("Who will win race 3?"), so the admin picks a day instead of typing every horse.
 *
 * The runners are copied into each field (bilingual choices, valued by the source's horse id), so
 * the form never waits for, or breaks with, the racing site; reload() brings them up to date
 * (by hand from the editor, and hourly by `forms:reload-race-runners` until the form closes).
 */
class RacePredictions
{
    public const LOCALES = ['en', 'ar'];

    public function __construct(private readonly RacingClient $racing) {}

    /**
     * The race days of this season and the next, for a select: Y-m-d => "Sat 24 Oct 2026 · 2nd Meeting".
     *
     * @return array<string, string>
     *
     * @throws RacingUnavailableException
     */
    public function raceDays(?string $today = null): array
    {
        $today ??= now(self::timezone())->format('Y-m-d');
        $next = Carbon::parse(RacingClient::seasonRange(RacingClient::seasonOf($today))['to'])->addDay()->format('Y-m-d');
        $days = $this->racing->raceDaysOfSeasonOf($today) + $this->racing->raceDaysOfSeasonOf($next);
        ksort($days);

        $options = [];

        foreach ($days as $date => $meeting) {
            $label = Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('D j M Y');
            $options[$date] = filled($meeting) ? "{$label} · {$meeting}" : $label;
        }

        return $options;
    }

    /**
     * The next race day from today (else the last one), to preselect.
     *
     * @throws RacingUnavailableException
     */
    public function defaultRaceDay(): ?string
    {
        return $this->racing->defaultRaceDay(now(self::timezone())->format('Y-m-d'));
    }

    /**
     * A day's races in running order, each numbered and named in every language.
     *
     * @return list<array{id: int, number: int, time: ?string, distance: ?int, name: array<string, string>}>
     *
     * @throws RacingUnavailableException
     */
    public function races(string $date): array
    {
        $byLocale = [];

        foreach (self::LOCALES as $locale) {
            $byLocale[$locale] = collect($this->racing->raceDay($date, $locale))->keyBy('id');
        }

        $races = $byLocale['en']->sortBy(fn (array $race): string => (string) ($race['time'] ?? ''))->values();

        return $races->map(fn (array $race, int $index): array => [
            'id' => (int) $race['id'],
            'number' => $index + 1,
            'time' => $race['time'] ?? null,
            'distance' => isset($race['distance']) ? (int) $race['distance'] : null,
            'name' => collect(self::LOCALES)->mapWithKeys(fn (string $locale): array => [
                $locale => (string) ($byLocale[$locale][$race['id']]['name'] ?? $race['name']),
            ])->all(),
        ])->all();
    }

    /**
     * A day's races for a select: id => "Race 1 · 13:30 · Sad Wadi Al Khoud Handicap · 1200M".
     *
     * @return array<int, string>
     *
     * @throws RacingUnavailableException
     */
    public function raceOptions(string $date): array
    {
        $locale = in_array(app()->getLocale(), self::LOCALES, true) ? app()->getLocale() : 'en';

        return collect($this->races($date))->mapWithKeys(fn (array $race): array => [
            $race['id'] => implode(' · ', array_filter([
                __('admin_race_predictions.race_number', ['number' => $race['number']]),
                $race['time'],
                $race['name'][$locale],
                $race['distance'] ? "{$race['distance']}M" : null,
            ])),
        ])->all();
    }

    /**
     * A race's runners as choices: from the race card once it is out, else from the entries.
     * The value is the source's horse id, so a renamed or renumbered horse stays the same answer;
     * the label is the horse's name. What the visitors' runner cards show besides (number, jockey,
     * weight, gate, silks) is under `details`, by horse id: the editor's choices list keeps only
     * values and labels.
     *
     * @return array{from: ?string, choices: list<array{value: string, label: array<string, string>}>, details: array<string, array<string, mixed>>}
     *
     * @throws RacingUnavailableException
     */
    public function runners(int $raceId): array
    {
        // an unknown id makes the source's card page answer with megabytes, so vet it first (as the site does)
        $info = $this->racing->raceInfo($raceId);
        $from = match (true) {
            ! $info['exists'] => null,
            $info['declarations'] > 0 => 'card',
            $info['entries'] > 0 => 'entries',
            default => null,
        };

        if ($from === null) {
            return ['from' => null, 'choices' => [], 'details' => []];
        }

        $choices = [];
        $details = [];

        foreach (self::LOCALES as $locale) {
            foreach ($this->horses($from, $raceId, $locale) as $horse) {
                $id = (string) $horse['id'];
                $choices[$id]['value'] = $id;
                $choices[$id]['label'][$locale] = $horse['name'];
                $details[$id]['no'] = $horse['no'];
                $details[$id]['gate'] = $horse['gate'];
                $details[$id]['weight'] = $horse['weight'];
                $details[$id]['image'] = $horse['image'];
                $details[$id]['jockey'][$locale] = $horse['jockey'];
            }
        }

        // a horse the other language's page left out still gets a label in every language
        $choices = array_map(function (array $choice): array {
            $fallback = $choice['label']['en'] ?? reset($choice['label']);
            $choice['label'] = collect(self::LOCALES)->mapWithKeys(fn (string $l): array => [$l => $choice['label'][$l] ?? $fallback])->all();

            return $choice;
        }, $choices);

        return ['from' => $from, 'choices' => array_values($choices), 'details' => $details];
    }

    /**
     * What the race card says about the race itself, in every language: time, distance, breed and
     * type (`info`), surface / weather / rail (`facts`), the conditions and the prize money. Null
     * when the source does not list the race.
     *
     * @return ?array<string, array{time: ?string, info: list<string>, facts: list<array{label: string, value: string}>, conditions: ?array{label: string, value: string}, prizes: list<array{place: string, amount: string}>}>
     *
     * @throws RacingUnavailableException
     */
    public function details(int $raceId, ?string $from): ?array
    {
        // the meeting shell is fetched once and cached for every race in it
        $page = $from ?? 'entries';
        $details = [];

        foreach (self::LOCALES as $locale) {
            $race = collect($this->racing->meeting($page, $raceId, $locale)['races'] ?? [])->firstWhere('id', $raceId);

            if ($race === null) {
                continue;
            }

            $info = array_values((array) ($race['info'] ?? []));
            $time = isset($info[0]) && preg_match('/^\d{1,2}:\d{2}$/', $info[0]) ? array_shift($info) : null;

            $details[$locale] = [
                'time' => $time,
                'info' => $info,
                'facts' => array_values((array) ($race['facts'] ?? [])),
                'conditions' => $race['conditions'] ?? null,
                'prizes' => array_values((array) ($race['prizes'] ?? [])),
            ];
        }

        return $details === [] ? null : $details;
    }

    /**
     * The builder item for one race's prediction field.
     *
     * @param  array{id: int, number: int, time: ?string, distance: ?int, name: array<string, string>}  $race
     * @return array{type: string, data: array<string, mixed>}
     *
     * @throws RacingUnavailableException
     */
    public function field(string $date, array $race): array
    {
        $runners = $this->runners($race['id']);

        return [
            'type' => RaceWinnerField::id(),
            'data' => [
                'key' => "race_{$race['number']}",
                'label' => $this->raceLabel($race),
                'required' => true,
                'width' => 'full',
                'race_date' => $date,
                'race_id' => $race['id'],
                'sync_runners' => true,
                'runners_from' => $runners['from'],
                'runners_updated_at' => now()->toIso8601String(),
                'choices' => $runners['choices'],
                'runners' => $runners['details'],
                'race_details' => $this->details($race['id'], $runners['from']),
            ],
        ];
    }

    /**
     * "Race 3 - Sad Wadi Khasab Handicap - 1200M" in every language.
     *
     * @param  array{number: int, distance: ?int, name: array<string, string>}  $race
     * @return array<string, string>
     */
    public function raceLabel(array $race): array
    {
        return collect(self::LOCALES)->mapWithKeys(fn (string $locale): array => [
            $locale => implode(' - ', array_filter([
                __('admin_race_predictions.race_number', ['number' => $race['number']], $locale),
                $race['name'][$locale],
                $race['distance'] ? "{$race['distance']}M" : null,
            ])),
        ])->all();
    }

    /**
     * A whole prediction form for a race day: the visitor's name and mobile, then one field per race;
     * it closes when the first race starts.
     *
     * @return array{name: array<string, string>, fields: list<array<string, mixed>>, closes_at: ?string}
     *
     * @throws RacingUnavailableException
     */
    public function form(string $date): array
    {
        $races = $this->races($date);
        $both = fn (string $key, array $replace = []): array => collect(self::LOCALES)
            ->mapWithKeys(fn (string $locale): array => [$locale => __("admin_race_predictions.{$key}", $replace, $locale)])->all();

        $fields = [
            ['type' => 'text', 'data' => ['key' => 'name', 'label' => $both('fields.name'), 'required' => true, 'width' => 'half']],
            // one entry per person: the same number (however typed) cannot send the form twice
            ['type' => 'phone', 'data' => ['key' => 'mobile', 'label' => $both('fields.mobile'), 'required' => true, 'width' => 'half', 'unique' => true]],
        ];

        foreach ($races as $race) {
            $fields[] = $this->field($date, $race);
        }

        $first = collect($races)->pluck('time')->filter()->sort()->first();

        return [
            'name' => collect(self::LOCALES)->mapWithKeys(fn (string $locale): array => [
                $locale => __('admin_race_predictions.form_name', [
                    'date' => Carbon::parse($date)->locale($locale)->translatedFormat('j F Y'),
                ], $locale),
            ])->all(),
            'fields' => $fields,
            // the source's times are the course's local time; forms store UTC
            'closes_at' => $first === null ? null : Carbon::parse("{$date} {$first}", self::timezone())->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The field's data with its runners reloaded from the source. Horses no longer running leave the
     * choices but are remembered under "withdrawn", so answers already given still read as a name.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RacingUnavailableException
     */
    public function reloaded(array $data): array
    {
        $raceId = (int) ($data['race_id'] ?? 0);

        if ($raceId <= 0) {
            return $data;
        }

        $runners = $this->runners($raceId);

        // nothing published (yet, or any more): keep what the field has rather than empty it
        if ($runners['choices'] === []) {
            return $data;
        }

        $current = array_column($runners['choices'], 'value');
        $withdrawn = collect([...array_values((array) ($data['withdrawn'] ?? [])), ...array_values((array) ($data['choices'] ?? []))])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null) && ! in_array((string) $row['value'], $current, true))
            ->keyBy(fn (array $row): string => (string) $row['value'])
            ->values()
            ->all();

        return [
            ...$data,
            'choices' => $runners['choices'],
            'withdrawn' => $withdrawn,
            'runners' => $runners['details'],
            'race_details' => $this->details($raceId, $runners['from']) ?? ($data['race_details'] ?? null),
            'runners_from' => $runners['from'],
            'runners_updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Reload the runners of every race prediction field of a form that is left in step with the source
     * and whose race is still to run. Returns how many fields changed; the form is saved only when one did.
     *
     * @throws RacingUnavailableException
     */
    public function reload(Form $form): int
    {
        $changed = 0;
        $fields = (array) $form->fields;
        $today = now(self::timezone())->format('Y-m-d');

        foreach ($fields as $index => $item) {
            if (($item['type'] ?? null) !== RaceWinnerField::id()
                || ! ($item['data']['sync_runners'] ?? true)
                || (string) ($item['data']['race_date'] ?? '') < $today) {
                continue;
            }

            $data = (array) ($item['data'] ?? []);
            $reloaded = $this->reloaded($data);

            if ($this->runnersOf($reloaded) !== $this->runnersOf($data)) {
                $fields[$index]['data'] = $reloaded;
                $changed++;
            }
        }

        if ($changed > 0) {
            $form->fields = $fields;
            $form->save();
        }

        return $changed;
    }

    /**
     * Whether a form has race prediction fields.
     */
    public static function hasRaceFields(Form $form): bool
    {
        return collect((array) $form->fields)->contains(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === RaceWinnerField::id());
    }

    /**
     * Whether a race of the form has no runners yet: the form then stays closed to visitors, since
     * a "who wins?" with nothing to pick could not be answered.
     */
    public static function waitingForRunners(Form $form): bool
    {
        return collect((array) $form->fields)->contains(fn (mixed $item): bool => is_array($item)
            && ($item['type'] ?? null) === RaceWinnerField::id()
            && array_filter((array) ($item['data']['choices'] ?? []), fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null)) === []);
    }

    private static function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    public static function timezone(): string
    {
        return (string) config('racing.timezone', 'Asia/Muscat');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private function runnersOf(array $data): array
    {
        return [array_values((array) ($data['choices'] ?? [])), $data['runners_from'] ?? null, $data['runners'] ?? null, $data['race_details'] ?? null];
    }

    /**
     * The horses of one race page, in its order. The entries have no number, jockey, gate or silks yet.
     *
     * @return list<array{id: int, name: string, no: ?string, gate: ?string, weight: ?string, jockey: ?string, image: ?string}>
     *
     * @throws RacingUnavailableException
     */
    private function horses(string $page, int $raceId, string $locale): array
    {
        $detail = $this->racing->raceDetail($page, $raceId, $locale);

        if (! ($detail['available'] ?? false)) {
            return [];
        }

        $horses = [];

        if ($page === 'card') {
            foreach ($detail['runners'] ?? [] as $runner) {
                $id = (int) ($runner['horse']['id'] ?? 0);
                $name = trim((string) ($runner['horse']['name'] ?? ''));

                if ($id > 0 && $name !== '') {
                    $horses[] = [
                        'id' => $id,
                        'name' => $name,
                        'no' => self::text($runner['no'] ?? null),
                        'gate' => self::text($runner['gate'] ?? null),
                        'weight' => self::text($runner['weight'] ?? null),
                        // a claim is part of the jockey's name on the source: "Hamed Al Bulushi (2.0 kg)"
                        'jockey' => self::text($runner['jockey']['name'] ?? null),
                        'image' => self::text($runner['image'] ?? null),
                    ];
                }
            }

            return $horses;
        }

        // the entries are a plain table: the horse is the cell linked to a horse profile
        foreach ($detail['table']['rows'] ?? [] as $row) {
            foreach ($row as $cell) {
                if (($cell['link']['type'] ?? null) === 'horse' && (int) ($cell['link']['id'] ?? 0) > 0 && filled($cell['text'] ?? null)) {
                    $horses[] = ['id' => (int) $cell['link']['id'], 'name' => trim((string) $cell['text']), 'no' => null, 'gate' => null, 'weight' => null, 'jockey' => null, 'image' => null];

                    break;
                }
            }
        }

        return $horses;
    }
}
