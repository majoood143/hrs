<?php

namespace Packstub\FormBuilder\Fields;

use App\Support\Localized;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Support\FieldConditions;

/**
 * One field of a form, as stored in the form's "fields" JSON and resolved
 * against the field type registry.
 */
final class Field
{
    /**
     * @param  array<string, mixed>  $options  Type-specific settings (choices, min, max, rows...).
     * @param  array<int, string>  $rules  Extra Laravel validation rules entered in the builder.
     * @param  array<string, mixed>  $data  The raw builder data, for anything a custom type stores.
     */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly ?string $placeholder = null,
        public readonly ?string $hint = null,
        public readonly bool $required = false,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly array $rules = [],
        public readonly string $width = 'full',
        public readonly array $data = [],
    ) {}

    /**
     * Build a field from one builder item: ['type' => 'text', 'data' => [...]].
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item, FieldTypeRegistry $registry): ?self
    {
        $type = $registry->find((string) ($item['type'] ?? ''));

        if ($type === null) {
            return null;
        }

        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $label = trim((string) Localized::value($data, 'label'));
        $key = self::normalizeKey((string) ($data['key'] ?? ''), self::keyLabel($data), $type);

        if ($key === '') {
            return null;
        }

        $reserved = ['key', 'label', 'placeholder', 'hint', 'required', 'default', 'rules', 'width', 'condition'];

        return new self(
            key: $key,
            type: $type,
            label: $label !== '' ? $label : $type->label(),
            placeholder: self::nullableString(Localized::value($data, 'placeholder')),
            hint: self::nullableString(Localized::value($data, 'hint')),
            required: $type->isInput() && (bool) ($data['required'] ?? false),
            default: $data['default'] ?? null,
            options: array_diff_key($data, array_flip($reserved)),
            rules: self::normalizeRules($data['rules'] ?? []),
            width: in_array($data['width'] ?? null, ['full', 'half'], true) ? $data['width'] : 'full',
            data: $data,
        );
    }

    /**
     * The label a blank key is made from: the English one, so the key is the same whatever
     * language the admin works in (an Arabic label slugs to a transliteration, or nothing).
     *
     * @param  array<string, mixed>  $data
     */
    public static function keyLabel(array $data): string
    {
        $label = $data['label'] ?? null;
        $english = is_array($label) ? trim((string) ($label['en'] ?? '')) : '';

        return $english !== '' ? $english : trim((string) Localized::value($data, 'label'));
    }

    public static function normalizeKey(string $key, string $label, FieldType $type): string
    {
        $key = Str::slug($key !== '' ? $key : $label, '_');

        if ($key === '' && ! $type->isInput()) {
            $key = $type::id();
        }

        return $key;
    }

    public function isInput(): bool
    {
        return $this->type->isInput();
    }

    /**
     * The value => label choices of a select, radio or checkbox list.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        $fixed = $this->type->fixedChoices();

        if ($fixed !== null) {
            return $fixed;
        }

        $choices = $this->options['choices'] ?? [];

        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $value => $label) {
            // A list of {value, label: {en, ar}} rows or a value => label map both work.
            if (is_array($label)) {
                $row = $label;
                $value = $row['value'] ?? null;
                $label = Localized::value($row, 'label') ?? $value;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $value] = (string) ($label ?? $value);
        }

        return $normalized;
    }

    /**
     * The complete validation rules for the field: required/nullable, the
     * type's own rules and the custom ones from the builder.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        foreach ([...$this->type->rules($this), ...$this->rules] as $rule) {
            if ($rule !== null && $rule !== '' && ! in_array($rule, $rules, true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function htmlId(string $prefix): string
    {
        return $prefix.'-'.Str::slug($this->key);
    }

    /**
     * The field as the JSON definition endpoint exposes it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type::id(),
            'input' => $this->isInput(),
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'hint' => $this->hint,
            'required' => $this->required,
            'default' => $this->default,
            'width' => $this->width,
            'multiple' => $this->type->acceptsMultiple(),
            'choices' => $this->type->hasChoices() ? $this->choices() : null,
            'options' => $this->options,
            'condition' => $this->condition(),
        ];
    }

    /**
     * "Show this field only when …", or null when the field is always shown.
     *
     * @return array{field: string, operator: string, value: ?string}|null
     */
    public function condition(): ?array
    {
        return FieldConditions::rule($this->data);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (! is_array($rules)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($rule): string => is_string($rule) ? trim($rule) : '',
            $rules,
        )));
    }
}
