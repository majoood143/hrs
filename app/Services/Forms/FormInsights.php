<?php

namespace App\Services\Forms;

use App\Enums\OrderStatus;
use App\Models\Country;
use App\Models\ServiceOrder;
use App\Services\Reports\IncomeStatement;
use App\Support\FormOrderSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * What the submissions of one form say: how many came in and when, and for every field that has a
 * fixed set of answers (choices, a tick box, a nationality, a number, a date) how the answers split.
 * Free text, emails, phones, files and links are never counted.
 *
 * The counting is done in PHP over the stored JSON (no database-specific JSON functions), once per
 * filter, and cached until a submission arrives or changes. The counts are kept by stored value, so
 * the labels are applied afterwards in whatever language is current. The admin "Insights" page and
 * the public "Form results chart" block both read from here, so their numbers always agree.
 */
class FormInsights
{
    public const PERIODS = ['all', 'today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];

    /** The field types that can be charted. */
    public const CHARTABLE = ['select', 'radio', 'conditional_radio', 'checkboxes', 'checkbox', 'nationality', 'number', 'date'];

    /** A number field with this many whole values or fewer gets one bar per value; otherwise ranges. */
    private const MAX_EXACT_NUMBERS = 15;

    private const NUMBER_BINS = 10;

    private const CACHE_SECONDS = 3600;

    /** @var array<string, mixed>|null */
    private ?array $tally = null;

    /** @var array<string, array{byName: array<string, string>, labels: array<string, string>}> by locale */
    private array $nationalities = [];

    public function __construct(
        public readonly Form $form,
        public readonly ?CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $orderStatus = null,
    ) {}

    /** "all" means since the form's first submission; the other periods are the income report's. */
    public static function forPeriod(Form $form, string $period, ?string $from = null, ?string $to = null, ?string $orderStatus = null): self
    {
        $orderStatus = OrderStatus::tryFrom((string) $orderStatus)?->value;

        if (! in_array($period, self::PERIODS, true) || $period === 'all') {
            return new self($form, null, CarbonImmutable::now()->endOfDay(), $orderStatus);
        }

        [$start, $end] = IncomeStatement::period($period, $from, $to);

        return new self($form, $start, $end, $orderStatus);
    }

    /** The same number of days just before (null for "all", which has nothing before it). */
    public function previous(): ?self
    {
        if ($this->from === null) {
            return null;
        }

        $days = (int) $this->from->diffInDays($this->to) + 1;

        return new self($this->form, $this->from->subDays($days)->startOfDay(), $this->from->subDay()->endOfDay(), $this->orderStatus);
    }

    public function isPaidForm(): bool
    {
        return FormOrderSettings::for($this->form)->hasService();
    }

    /** @return Builder<FormSubmission> the submissions in scope (period, order status) */
    public function submissions(): Builder
    {
        $model = FormBuilder::submissionModel();

        return $model::query()
            ->where('form_id', $this->form->getKey())
            ->when($this->from, fn (Builder $q, CarbonImmutable $from) => $q->where('created_at', '>=', $from))
            ->where('created_at', '<=', $this->to)
            ->when($this->orderStatus, fn (Builder $q, string $status) => $q->whereIn('id', ServiceOrder::query()
                ->where('form_id', $this->form->getKey())
                ->where('status', $status)
                ->whereNotNull('submission_id')
                ->select('submission_id')));
    }

    /**
     * The fields that can be charted, in form order.
     *
     * @return Collection<string, Field>
     */
    public function chartableFields(): Collection
    {
        return $this->form->inputFields()->filter(fn (Field $field): bool => in_array($field->type::id(), self::CHARTABLE, true));
    }

    public function total(): int
    {
        return $this->tally()['total'];
    }

    public function unread(): int
    {
        return $this->submissions()->whereNull('read_at')->count();
    }

    public function lastSubmittedAt(): ?CarbonImmutable
    {
        $last = $this->submissions()->max('created_at');

        return $last ? CarbonImmutable::parse($last) : null;
    }

    /** The first day the charts start from: the period's, or the first submission's for "all". */
    public function start(): CarbonImmutable
    {
        if ($this->from !== null) {
            return $this->from;
        }

        $first = array_key_first($this->tally()['days']);

        return $first !== null ? CarbonImmutable::parse($first)->startOfDay() : $this->to->startOfDay();
    }

    /** "day" up to a month, "week" up to four months, else "month" (as the stable insights). */
    public function bucket(): string
    {
        $days = (int) $this->start()->diffInDays($this->to) + 1;

        return $days <= 31 ? 'day' : ($days <= 124 ? 'week' : 'month');
    }

    /**
     * Submissions per day/week/month, every bucket of the period present.
     *
     * @return Collection<string, array{label: string, count: int}>
     */
    public function timeline(): Collection
    {
        $bucket = $this->bucket();
        $locale = app()->getLocale();
        $key = fn (CarbonImmutable $date): string => match ($bucket) {
            'day' => $date->toDateString(),
            'week' => $date->startOfWeek()->toDateString(),
            default => $date->format('Y-m'),
        };

        $series = collect();

        for ($cursor = $this->start(); $cursor->lte($this->to); $cursor = match ($bucket) {
            'day' => $cursor->addDay(),
            'week' => $cursor->startOfWeek()->addWeek(),
            default => $cursor->startOfMonth()->addMonth(),
        }) {
            $series->put($key($cursor), [
                'label' => match ($bucket) {
                    'day', 'week' => ($bucket === 'week' ? $cursor->startOfWeek() : $cursor)->locale($locale)->translatedFormat('j M'),
                    default => $cursor->locale($locale)->translatedFormat('M Y'),
                },
                'count' => 0,
            ]);
        }

        foreach ($this->tally()['days'] as $day => $count) {
            $k = $key(CarbonImmutable::parse($day));

            if ($series->has($k)) {
                $series->put($k, ['label' => $series->get($k)['label'], 'count' => $series->get($k)['count'] + $count]);
            }
        }

        return $series;
    }

    /**
     * The answers of every chartable field, labelled in the current language.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function fieldResults(): Collection
    {
        return $this->chartableFields()
            ->map(fn (Field $field): ?array => $this->fieldResult($field->key))
            ->filter();
    }

    /**
     * One field's answers. `kind` is choices | multi | boolean | nationality | number | date; `rows`
     * are [key, label, count, share] (share = % of those who answered; for "multi" they can add up
     * past 100). A number also has `stats` (min, max, mean, median).
     *
     * @return array<string, mixed>|null
     */
    public function fieldResult(string $key): ?array
    {
        $field = $this->chartableFields()->get($key);

        if ($field === null) {
            return null;
        }

        $tally = $this->tally()['fields'][$key] ?? ['answered' => 0, 'counts' => [], 'numbers' => [], 'months' => []];
        $type = $field->type::id();
        $answered = (int) $tally['answered'];

        $result = [
            'key' => $key,
            'label' => $field->label,
            'type' => $type,
            'kind' => match ($type) {
                'checkboxes' => 'multi',
                'checkbox' => 'boolean',
                'nationality', 'number', 'date' => $type,
                default => 'choices',
            },
            'answered' => $answered,
            'total' => $this->total(),
            'rows' => [],
        ];

        $share = fn (int $count): float => $answered > 0 ? round($count / $answered * 100, 1) : 0.0;

        $result['rows'] = match ($result['kind']) {
            'choices', 'multi' => $this->choiceRows($field, $tally['counts'], $share),
            'boolean' => [
                ['key' => '1', 'label' => __('admin_form_insights.answers.ticked'), 'count' => (int) ($tally['counts']['1'] ?? 0), 'share' => $share((int) ($tally['counts']['1'] ?? 0))],
                ['key' => '0', 'label' => __('admin_form_insights.answers.not_ticked'), 'count' => (int) ($tally['counts']['0'] ?? 0), 'share' => $share((int) ($tally['counts']['0'] ?? 0))],
            ],
            'nationality' => $this->nationalityRows($tally['counts'], $share),
            'number' => $this->numberRows($tally['numbers'], $share),
            'date' => $this->dateRows($tally['months'], $share),
        };

        if ($result['kind'] === 'number') {
            $result['stats'] = $this->numberStats($tally['numbers']);
        }

        return $result;
    }

    /**
     * Orders of a paid form created in the period, by status (every status listed, in workflow order).
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function ordersByStatus(): array
    {
        $counts = ServiceOrder::query()
            ->where('form_id', $this->form->getKey())
            ->when($this->from, fn (Builder $q, CarbonImmutable $from) => $q->where('created_at', '>=', $from))
            ->where('created_at', '<=', $this->to)
            ->when($this->orderStatus, fn (Builder $q, string $status) => $q->where('status', $status))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): array => ['key' => $status->value, 'label' => $status->label(), 'count' => (int) ($counts[$status->value] ?? 0)])
            ->values()
            ->all();
    }

    /** The money of the form's orders paid in the period: the income report's own figures. */
    public function income(): IncomeStatement
    {
        // "all": from the form's creation or its first submission, whichever is earlier (imported old data)
        $from = $this->from ?? collect([$this->start(), $this->form->created_at ? CarbonImmutable::instance($this->form->created_at)->startOfDay() : null])->filter()->min();

        return new IncomeStatement($from, $this->to, formId: (int) $this->form->getKey());
    }

    // ------------------------------------------------------------------
    // Counting
    // ------------------------------------------------------------------

    /** @return array{total: int, days: array<string, int>, fields: array<string, array<string, mixed>>} */
    private function tally(): array
    {
        return $this->tally ??= Cache::remember($this->cacheKey(), self::CACHE_SECONDS, fn (): array => $this->count());
    }

    /** Changes whenever a submission (or, with a status filter, an order) of the form is added or changed. */
    private function cacheKey(): string
    {
        $model = FormBuilder::submissionModel();
        $stamp = $model::query()->where('form_id', $this->form->getKey())
            ->selectRaw('count(*) as c, max(id) as m, max(updated_at) as u')
            ->toBase()
            ->first();

        $orders = $this->orderStatus
            ? ServiceOrder::query()->where('form_id', $this->form->getKey())->max('updated_at')
            : null;

        return 'form-insights:'.$this->form->getKey().':'.md5(json_encode([
            $this->from?->toIso8601String(),
            $this->to->toIso8601String(),
            $this->orderStatus,
            $this->form->updated_at?->toIso8601String(),
            $stamp?->c, $stamp?->m, $stamp?->u,
            $orders,
        ]));
    }

    /** @return array{total: int, days: array<string, int>, fields: array<string, array<string, mixed>>} */
    private function count(): array
    {
        $fields = $this->chartableFields();
        $tally = ['total' => 0, 'days' => [], 'fields' => []];

        foreach ($fields as $key => $field) {
            $tally['fields'][$key] = ['answered' => 0, 'counts' => [], 'numbers' => [], 'months' => []];
        }

        $this->submissions()
            ->select(['id', 'data', 'created_at'])
            ->lazyById(500)
            ->each(function (FormSubmission $submission) use ($fields, &$tally): void {
                $tally['total']++;
                $day = $submission->created_at?->toDateString();

                if ($day !== null) {
                    $tally['days'][$day] = ($tally['days'][$day] ?? 0) + 1;
                }

                $data = is_array($submission->data) ? $submission->data : [];

                foreach ($fields as $key => $field) {
                    if (array_key_exists($key, $data)) {
                        $this->countValue($tally['fields'][$key], $field, $data[$key]);
                    }
                }
            });

        ksort($tally['days']);

        return $tally;
    }

    /** @param  array<string, mixed>  $slot */
    private function countValue(array &$slot, Field $field, mixed $value): void
    {
        $type = $field->type::id();

        if ($type === 'conditional_radio' && is_array($value)) {
            $value = $value['answer'] ?? null;
        }

        switch ($type) {
            case 'checkbox':
                if ($value === null || $value === '') {
                    return;
                }
                $bucket = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                $slot['answered']++;
                $slot['counts'][$bucket] = ($slot['counts'][$bucket] ?? 0) + 1;

                return;

            case 'checkboxes':
                $picked = collect(is_array($value) ? $value : [$value])
                    ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
                    ->map(fn ($v) => trim((string) $v))
                    ->unique();

                if ($picked->isEmpty()) {
                    return;
                }
                $slot['answered']++;

                foreach ($picked as $choice) {
                    $slot['counts'][$choice] = ($slot['counts'][$choice] ?? 0) + 1;
                }

                return;

            case 'number':
                if (is_numeric($value)) {
                    $slot['answered']++;
                    $slot['numbers'][] = $value + 0;
                }

                return;

            case 'date':
                $date = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', substr(trim($value), 0, 10)) : false;

                if ($date !== false && $date !== null) {
                    $slot['answered']++;
                    $month = $date->format('Y-m');
                    $slot['months'][$month] = ($slot['months'][$month] ?? 0) + 1;
                }

                return;

            default:
                if (! is_scalar($value) || trim((string) $value) === '') {
                    return;
                }
                $choice = trim((string) $value);

                if ($type === 'nationality') {
                    $choice = $this->nationalityKey($choice);
                }
                $slot['answered']++;
                $slot['counts'][$choice] = ($slot['counts'][$choice] ?? 0) + 1;
        }
    }

    // ------------------------------------------------------------------
    // Rows
    // ------------------------------------------------------------------

    /**
     * The form's choices in their own order (zeros included), then answers that are no longer a
     * choice (a choice deleted after people picked it), labelled with the stored value.
     *
     * @param  array<string, int>  $counts
     * @return array<int, array{key: string, label: string, count: int, share: float}>
     */
    private function choiceRows(Field $field, array $counts, \Closure $share): array
    {
        $rows = [];

        foreach ($field->choices() as $value => $label) {
            $count = (int) ($counts[(string) $value] ?? 0);
            $rows[] = ['key' => (string) $value, 'label' => $label, 'count' => $count, 'share' => $share($count)];
            unset($counts[(string) $value]);
        }

        arsort($counts);

        foreach ($counts as $value => $count) {
            $rows[] = ['key' => (string) $value, 'label' => (string) $value, 'count' => (int) $count, 'share' => $share((int) $count)];
        }

        return $rows;
    }

    /**
     * Most common first; `code` is the country's ISO code for the map (null for an answer that no
     * longer matches a country).
     *
     * @param  array<string, int>  $counts
     * @return array<int, array{key: string, label: string, count: int, share: float, code: ?string}>
     */
    private function nationalityRows(array $counts, \Closure $share): array
    {
        arsort($counts);
        $labels = $this->nationalities()['labels'];

        return collect($counts)
            ->map(fn (int $count, string $key): array => [
                'key' => $key,
                'label' => str_starts_with($key, 'code:') ? ($labels[substr($key, 5)] ?? substr($key, 5)) : $key,
                'count' => $count,
                'share' => $share($count),
                'code' => str_starts_with($key, 'code:') ? substr($key, 5) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * One bar per whole value when there are few of them, else equal ranges with round edges.
     *
     * @param  array<int, int|float>  $numbers
     * @return array<int, array{key: string, label: string, count: int, share: float}>
     */
    private function numberRows(array $numbers, \Closure $share): array
    {
        if ($numbers === []) {
            return [];
        }

        $min = min($numbers);
        $max = max($numbers);
        $whole = collect($numbers)->every(fn ($n) => floor($n) == $n);
        $format = fn (int|float $n): string => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');

        if ($whole && $max - $min + 1 <= self::MAX_EXACT_NUMBERS) {
            $counts = array_count_values(array_map(fn ($n) => (int) $n, $numbers));
            $rows = [];

            for ($n = (int) $min; $n <= (int) $max; $n++) {
                $rows[] = ['key' => (string) $n, 'label' => $format($n), 'count' => (int) ($counts[$n] ?? 0), 'share' => $share((int) ($counts[$n] ?? 0))];
            }

            return $rows;
        }

        $step = $this->niceStep(($max - $min) / self::NUMBER_BINS);
        $start = floor($min / $step) * $step;
        $bins = max(1, (int) ceil(($max - $start) / $step + 1e-9));

        if ($start + $bins * $step <= $max) {
            $bins++;
        }

        $counts = array_fill(0, $bins, 0);

        foreach ($numbers as $n) {
            $counts[min($bins - 1, (int) floor(($n - $start) / $step))]++;
        }

        $rows = [];

        foreach ($counts as $i => $count) {
            $low = $start + $i * $step;
            $rows[] = ['key' => (string) $low, 'label' => $format($low).'–'.$format($low + $step), 'count' => $count, 'share' => $share($count)];
        }

        return $rows;
    }

    /** 1, 2 or 5 times a power of ten, at least the raw step. */
    private function niceStep(float $raw): float
    {
        if ($raw <= 0) {
            return 1;
        }

        $power = 10 ** floor(log10($raw));

        foreach ([1, 2, 5, 10] as $m) {
            if ($m * $power >= $raw) {
                return $m * $power;
            }
        }

        return 10 * $power;
    }

    /**
     * @param  array<int, int|float>  $numbers
     * @return array{min: int|float, max: int|float, mean: float, median: float}|null
     */
    private function numberStats(array $numbers): ?array
    {
        if ($numbers === []) {
            return null;
        }

        sort($numbers);
        $count = count($numbers);
        $middle = intdiv($count, 2);

        return [
            'min' => $numbers[0],
            'max' => $numbers[$count - 1],
            'mean' => round(array_sum($numbers) / $count, 2),
            'median' => round($count % 2 ? $numbers[$middle] : ($numbers[$middle - 1] + $numbers[$middle]) / 2, 2),
        ];
    }

    /**
     * Per month from the earliest answered month to the latest; per year past three years.
     *
     * @param  array<string, int>  $months
     * @return array<int, array{key: string, label: string, count: int, share: float}>
     */
    private function dateRows(array $months, \Closure $share): array
    {
        if ($months === []) {
            return [];
        }

        ksort($months);
        $locale = app()->getLocale();
        $first = CarbonImmutable::createFromFormat('!Y-m', array_key_first($months));
        $last = CarbonImmutable::createFromFormat('!Y-m', array_key_last($months));
        $byYear = $first->diffInMonths($last) >= 36;
        $rows = [];

        for ($cursor = $first; $cursor->lte($last); $cursor = $byYear ? $cursor->addYear()->startOfYear() : $cursor->addMonth()) {
            $count = $byYear
                ? (int) collect($months)->filter(fn (int $n, string $m) => str_starts_with($m, $cursor->format('Y')))->sum()
                : (int) ($months[$cursor->format('Y-m')] ?? 0);
            $rows[] = [
                'key' => $byYear ? $cursor->format('Y') : $cursor->format('Y-m'),
                'label' => $byYear ? $cursor->format('Y') : $cursor->locale($locale)->translatedFormat('M Y'),
                'count' => $count,
                'share' => $share($count),
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Nationalities
    // ------------------------------------------------------------------

    /**
     * A nationality is stored as its name in the visitor's language ("Omani" or "عماني"), so both
     * spellings are folded into the country ("code:OM"); a name that matches no country stays as typed.
     */
    private function nationalityKey(string $name): string
    {
        $code = $this->nationalities()['byName'][mb_strtolower($name)] ?? null;

        return $code !== null ? 'code:'.$code : $name;
    }

    /** @return array{byName: array<string, string>, labels: array<string, string>} */
    private function nationalities(): array
    {
        $locale = app()->getLocale();

        if (isset($this->nationalities[$locale])) {
            return $this->nationalities[$locale];
        }

        $byName = [];
        $labels = [];
        $arabic = $locale === 'ar';

        foreach (Country::query()->whereNotNull('country_code')->get(['country_code', 'nationality_en', 'nationality_ar']) as $country) {
            $code = strtoupper((string) $country->country_code);

            foreach ([$country->nationality_en, $country->nationality_ar] as $name) {
                if (filled($name)) {
                    $byName[mb_strtolower(trim($name))] ??= $code;
                }
            }

            $labels[$code] ??= ($arabic ? $country->nationality_ar : $country->nationality_en) ?: ($country->nationality_en ?: $code);
        }

        return $this->nationalities[$locale] = ['byName' => $byName, 'labels' => $labels];
    }
}
