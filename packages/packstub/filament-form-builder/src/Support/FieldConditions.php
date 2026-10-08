<?php

namespace Packstub\FormBuilder\Support;

use App\Support\Localized;
use Illuminate\Support\Collection;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;

/**
 * "Show this field only when …": one rule per field, stored in its data as
 * condition: {field, operator, value}, pointing at an earlier field.
 *
 * The server is the judge: a field whose rule does not hold is neither validated nor
 * stored (its value is saved as null), whatever the browser showed. The Blade renderer's
 * script and the Livewire renderer only mirror the same rules for the visitor; without
 * JavaScript every field is shown and the server still ignores the ones that do not apply.
 * Rules are worked out in field order, and a field hidden by its own rule counts as
 * unanswered for the fields that depend on it.
 */
class FieldConditions
{
    public const OPERATORS = ['is', 'is_not', 'filled', 'empty'];

    /** Operators that compare against a value (the others only ask whether there is one). */
    public const VALUE_OPERATORS = ['is', 'is_not'];

    /** Types that cannot drive a rule: nothing the visitor chooses (hidden), or a file. */
    public const SOURCE_EXCLUDED = ['hidden', 'file'];

    /**
     * The field's rule, or null when it has none (or an incomplete one).
     *
     * @param  array<string, mixed>  $data  A field's builder data.
     * @return array{field: string, operator: string, value: ?string}|null
     */
    public static function rule(array $data): ?array
    {
        $condition = $data['condition'] ?? null;

        if (! is_array($condition) || blank($condition['field'] ?? null)) {
            return null;
        }

        $operator = in_array($condition['operator'] ?? null, self::OPERATORS, true) ? $condition['operator'] : 'is';
        // the editor saves a picked choice as "choice" and a typed value as "value"
        $raw = filled($condition['choice'] ?? null) ? $condition['choice'] : ($condition['value'] ?? null);
        $value = is_scalar($raw) ? trim((string) $raw) : null;

        if (in_array($operator, self::VALUE_OPERATORS, true) && ($value === null || $value === '')) {
            return null;
        }

        return ['field' => (string) $condition['field'], 'operator' => $operator, 'value' => $value];
    }

    /**
     * The keys of the fields that apply for these answers.
     *
     * @param  array<string, mixed>  $values  Raw input or Livewire state, keyed by field key.
     * @return array<int, string>
     */
    public static function visibleKeys(Form $form, array $values): array
    {
        return static::visibleKeysOf($form->fieldList(), $values);
    }

    /**
     * @param  Collection<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    public static function visibleKeysOf(Collection $fields, array $values): array
    {
        $byKey = $fields->keyBy('key');
        $hidden = [];

        foreach ($fields as $field) {
            $rule = static::rule($field->data);

            if ($rule === null) {
                continue;
            }

            $source = $byKey->get($rule['field']);

            // a rule on a field that no longer exists never hides anything
            if ($source === null) {
                continue;
            }

            $answers = isset($hidden[$source->key]) ? [] : static::answers($values[$source->key] ?? null, $source);

            if (! static::holds($rule, $answers)) {
                $hidden[$field->key] = true;
            }
        }

        return $fields->pluck('key')->reject(fn (string $key): bool => isset($hidden[$key]))->values()->all();
    }

    /**
     * @param  array{field: string, operator: string, value: ?string}  $rule
     * @param  array<int, string>  $answers
     */
    public static function holds(array $rule, array $answers): bool
    {
        return match ($rule['operator']) {
            'is' => in_array((string) $rule['value'], $answers, true),
            'is_not' => ! in_array((string) $rule['value'], $answers, true),
            'filled' => $answers !== [],
            'empty' => $answers === [],
            default => true,
        };
    }

    /**
     * A field's answer as a list of strings: the choices picked, the text typed, "1" for a
     * ticked box, the answer part of a radio-with-details; [] when nothing was given.
     *
     * @return array<int, string>
     */
    public static function answers(mixed $value, Field $source): array
    {
        if ($source->type::id() === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? ['1'] : [];
        }

        if (is_array($value) && array_key_exists('answer', $value)) {
            $value = $value['answer'];
        }

        $list = is_array($value) ? array_values($value) : [$value];

        return array_values(array_filter(
            array_map(fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $list),
            fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * What the editor offers a field to depend on: the input fields before it.
     *
     * @param  array<array-key, mixed>  $items  The builder's items, in order.
     * @return array<string, array{label: string, type: string, item: array<string, mixed>}> Keyed by field key.
     */
    public static function sourcesBefore(array $items, ?string $itemKey): array
    {
        $registry = app(FieldTypeRegistry::class);
        $sources = [];

        foreach ($items as $key => $item) {
            if ((string) $key === (string) $itemKey) {
                break;
            }

            $type = is_array($item) ? $registry->find((string) ($item['type'] ?? '')) : null;
            $data = is_array($item['data'] ?? null) ? $item['data'] : [];

            if ($type === null || ! $type->isInput() || in_array($type::id(), self::SOURCE_EXCLUDED, true)) {
                continue;
            }

            $fieldKey = Field::normalizeKey((string) ($data['key'] ?? ''), Field::keyLabel($data), $type);

            if ($fieldKey === '') {
                continue;
            }

            $sources[$fieldKey] = [
                'label' => trim((string) Localized::value($data, 'label')) ?: $type->label(),
                'type' => $type::id(),
                'item' => $item,
            ];
        }

        return $sources;
    }
}
