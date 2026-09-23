<?php

namespace Packstub\FormBuilder\Fields\Types;

use App\Filament\Support\TranslatableInput;
use App\Support\Localized;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Concerns\HasChoices;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * Radio buttons with a follow-up text box that only appears for some of the
 * answers ("Did you travel last year?" No / Yes → "Where to?"). One field,
 * one key: the value is stored as ['answer' => 'yes', 'details' => 'Italy'],
 * and the details are dropped whenever the answer does not reveal them.
 */
class ConditionalRadioField extends FieldType
{
    use HasChoices;

    public static function id(): string
    {
        return 'conditional_radio';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-turn-down-right';
    }

    public function editorSchema(): array
    {
        return [
            $this->choicesRepeater(live: true),
            Select::make('reveal_on')
                ->label(__('packstub-form-builder::form-builder.editor.reveal_on'))
                ->helperText(__('packstub-form-builder::form-builder.editor.reveal_on_hint'))
                ->options(fn (Get $get): array => collect((array) $get('choices'))
                    ->filter(fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null))
                    ->mapWithKeys(fn (array $row): array => [(string) $row['value'] => (string) (Localized::value($row, 'label') ?? $row['value'])])
                    ->all())
                ->multiple()
                ->required()
                ->native(false)
                ->columnSpanFull(),
            TranslatableInput::grid(fn (string $locale, array $meta): TextInput => TextInput::make("details_label.{$locale}")
                ->label(__('packstub-form-builder::form-builder.editor.details_label').' ('.$meta['native'].')')
                ->required($locale === TranslatableInput::defaultLocale())
                ->maxLength(255))->columnSpanFull(),
            TranslatableInput::grid(fn (string $locale, array $meta): TextInput => TextInput::make("details_placeholder.{$locale}")
                ->label(__('packstub-form-builder::form-builder.editor.details_placeholder').' ('.$meta['native'].')')
                ->maxLength(255))->columnSpanFull(),
            Select::make('details_type')
                ->label(__('packstub-form-builder::form-builder.editor.details_type'))
                ->options([
                    'text' => __('packstub-form-builder::form-builder.types.text'),
                    'textarea' => __('packstub-form-builder::form-builder.types.textarea'),
                ])
                ->default('text')
                ->formatStateUsing(fn (?string $state): string => $state ?: 'text')
                ->native(false),
            TextInput::make('details_max_length')
                ->label(__('packstub-form-builder::form-builder.editor.max_length'))
                ->integer()
                ->minValue(1),
            Toggle::make('details_required')
                ->label(__('packstub-form-builder::form-builder.editor.details_required'))
                ->default(true)
                ->inline(false),
        ];
    }

    public function rules(Field $field): array
    {
        return ['array'];
    }

    public function nestedRules(Field $field): array
    {
        $answer = [$field->required ? 'required' : 'nullable', 'string'];

        if (($values = array_keys($field->choices())) !== []) {
            $answer[] = Rule::in($values);
        }

        $details = ['nullable', 'string', 'max:'.static::detailsMaxLength($field)];
        $triggers = static::triggers($field);

        if (static::detailsRequired($field) && $triggers !== []) {
            // Quoted: required_if splits its parameters as CSV and a choice value may hold a comma.
            array_unshift($details, 'required_if:'.$field->key.'.answer,'.implode(',', array_map(
                fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
                $triggers,
            )));
        }

        return ['answer' => $answer, 'details' => $details];
    }

    public function nestedAttributes(Field $field): array
    {
        return ['answer' => $field->label, 'details' => static::detailsLabel($field)];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        if (! is_array($value)) {
            $value = ['answer' => $value];
        }

        $answer = is_scalar($value['answer'] ?? null) ? trim((string) $value['answer']) : '';

        if ($answer === '') {
            return null;
        }

        $details = is_scalar($value['details'] ?? null) ? trim((string) $value['details']) : '';

        return [
            'answer' => $answer,
            // Typed in, then the answer changed to one that hides the box: not kept.
            'details' => static::reveals($field, $answer) && $details !== '' ? $details : null,
        ];
    }

    public function format(mixed $value, Field $field): string
    {
        if (! is_array($value)) {
            return parent::format($value, $field);
        }

        $answer = (string) ($value['answer'] ?? '');

        if ($answer === '') {
            return '';
        }

        $text = (string) ($field->choices()[$answer] ?? $answer);
        $details = trim((string) ($value['details'] ?? ''));

        return $details === '' ? $text : $text.' — '.$details;
    }

    public function formComponent(Field $field): Component
    {
        $triggers = static::triggers($field);

        $answer = Radio::make('answer')
            ->label($field->label)
            ->options($field->choices())
            ->required($field->required)
            ->in(array_keys($field->choices()))
            ->live();

        if ($field->hint !== null) {
            $answer->helperText($field->hint);
        }

        if (is_scalar($field->default) && $field->default !== '') {
            $answer->default((string) $field->default);
        }

        $details = static::detailsType($field) === 'textarea'
            ? Textarea::make('details')->rows(3)
            : TextInput::make('details');

        $details
            ->label(static::detailsLabel($field))
            ->placeholder(static::detailsPlaceholder($field))
            ->maxLength(static::detailsMaxLength($field))
            ->required(static::detailsRequired($field))
            ->visible(fn (Get $get): bool => in_array((string) $get('answer'), $triggers, true));

        return Group::make([$answer, $details])
            ->statePath($field->key)
            ->columnSpan($field->width === 'half' ? 1 : 2);
    }

    /**
     * The answers that reveal the details box, limited to existing choices.
     *
     * @return array<int, string>
     */
    public static function triggers(Field $field): array
    {
        $choices = $field->choices();

        return array_values(array_filter(
            array_map('strval', (array) $field->option('reveal_on', [])),
            fn (string $value): bool => array_key_exists($value, $choices),
        ));
    }

    public static function reveals(Field $field, ?string $answer): bool
    {
        return $answer !== null && in_array($answer, static::triggers($field), true);
    }

    public static function detailsLabel(Field $field): string
    {
        $label = trim((string) Localized::value($field->options, 'details_label'));

        return $label !== '' ? $label : __('packstub-form-builder::form-builder.frontend.details');
    }

    public static function detailsPlaceholder(Field $field): ?string
    {
        $placeholder = trim((string) Localized::value($field->options, 'details_placeholder'));

        return $placeholder === '' ? null : $placeholder;
    }

    public static function detailsType(Field $field): string
    {
        return $field->option('details_type') === 'textarea' ? 'textarea' : 'text';
    }

    public static function detailsRequired(Field $field): bool
    {
        return (bool) $field->option('details_required', true);
    }

    public static function detailsMaxLength(Field $field): int
    {
        $max = $field->option('details_max_length');

        return filled($max) ? (int) $max : (static::detailsType($field) === 'textarea' ? 5000 : 255);
    }
}
