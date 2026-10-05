<?php

namespace App\Support;

use App\Services\Forms\FormChart;
use App\Services\Forms\FormInsights;
use Carbon\CarbonImmutable;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;

/**
 * The public "Form results chart" CMS block: one field of one form, charted for visitors.
 *
 * What may be shown is narrow on purpose: only fields with a fixed set of answers (never free text,
 * emails, phones or files), never a form linked to a paid service (its answers are customers' orders),
 * nothing until at least MIN_ANSWERS people answered, and answers picked by fewer than RARE people are
 * folded into "Other", so no single visitor's answer can be picked out.
 */
class FormChartBlock
{
    /** The fewest answers before anything is shown (an admin may raise it, never lower it). */
    public const MIN_ANSWERS = 5;

    /** A choice picked by fewer people than this is folded into "Other". */
    public const RARE = 3;

    public const STYLES = ['auto', 'bar', 'donut', 'map'];

    /** May this form be charted in public? */
    public static function allows(?Form $form): bool
    {
        return $form !== null && ! FormOrderSettings::for($form)->hasService();
    }

    /** @return array<int, string> forms that may be charted, id => name */
    public static function formOptions(): array
    {
        $model = FormBuilder::formModel();

        return $model::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Form $form) => self::allows($form))
            ->mapWithKeys(fn (Form $form) => [$form->getKey() => (string) $form->name])
            ->all();
    }

    /** @return array<string, string> the form's chartable fields, key => label */
    public static function fieldOptions(mixed $formId): array
    {
        $form = self::form($formId);

        if (! self::allows($form)) {
            return [];
        }

        return (new FormInsights($form, null, CarbonImmutable::now()))
            ->chartableFields()
            ->map(fn (Field $field) => $field->label)
            ->all();
    }

    /**
     * What the block shows, or null when it shows nothing (no form, a paid form, a field that is gone).
     * `spec` is null while too few people have answered; `waiting` then says how many are needed.
     *
     * @param  array<string, mixed>  $data  the block's saved data
     * @return array{heading: string, subheading: ?string, spec: ?array, answered: int, waiting: int, table: bool}|null
     */
    public static function resolve(array $data): ?array
    {
        $form = self::form($data['form_id'] ?? null);

        if (! self::allows($form)) {
            return null;
        }

        $from = self::date($data['date_from'] ?? null)?->startOfDay();
        $to = self::date($data['date_to'] ?? null)?->endOfDay() ?? CarbonImmutable::now()->endOfDay();
        $result = (new FormInsights($form, $from, $to))->fieldResult((string) ($data['field_key'] ?? ''));

        if ($result === null) {
            return null;
        }

        $minimum = max(self::MIN_ANSWERS, (int) ($data['min_answers'] ?? self::MIN_ANSWERS));
        $style = in_array($data['style'] ?? null, self::STYLES, true) ? $data['style'] : 'auto';
        $answered = (int) $result['answered'];

        return [
            'heading' => Localized::value($data, 'heading') ?: $result['label'],
            'subheading' => Localized::value($data, 'subheading'),
            'spec' => $answered >= $minimum
                ? FormChart::forField(self::foldRare($result), $style === 'map' ? 'auto' : $style, ($data['display'] ?? 'count') === 'percent', $style === 'map')
                : null,
            'answered' => $answered,
            'waiting' => $minimum,
            'table' => (bool) ($data['show_table'] ?? true),
        ];
    }

    /**
     * Answers given by fewer than RARE people join "Other" (choice fields and nationalities: a number
     * range or a month says little about one person). Answers nobody gave stay, they reveal nothing.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function foldRare(array $result): array
    {
        if (! in_array($result['kind'], ['choices', 'multi', 'nationality'], true)) {
            return $result;
        }

        $kept = [];
        $rare = 0;

        foreach ($result['rows'] as $row) {
            if ($row['count'] > 0 && $row['count'] < self::RARE) {
                $rare += $row['count'];
            } else {
                $kept[] = $row;
            }
        }

        if ($rare > 0) {
            $kept[] = FormChart::otherRow($rare, (int) $result['answered']);
        }

        return ['rows' => $kept] + $result;
    }

    private static function form(mixed $id): ?Form
    {
        if (! is_numeric($id)) {
            return null;
        }

        $model = FormBuilder::formModel();

        return $model::query()->find((int) $id);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
