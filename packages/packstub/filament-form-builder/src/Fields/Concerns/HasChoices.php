<?php

namespace Packstub\FormBuilder\Fields\Concerns;

use App\Support\Localized;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Filament\EditorLanguages;

trait HasChoices
{
    public function hasChoices(): bool
    {
        return true;
    }

    /**
     * @return array<int, Component>
     */
    public function editorSchema(): array
    {
        return [$this->choicesRepeater()];
    }

    /**
     * The choices editor. Live values let other settings of the block (a
     * select of these choices) follow them as they are typed.
     *
     * The labels come first; an empty value is filled from the English label
     * (as a field's key is), and an existing value is never changed, since
     * stored submissions refer to it.
     */
    protected function choicesRepeater(bool $live = false): Repeater
    {
        return Repeater::make('choices')
            ->label(__('packstub-form-builder::form-builder.editor.choices'))
            ->schema([
                EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("label.{$locale}")
                    ->label(__('packstub-form-builder::form-builder.editor.choice_label').' ('.$meta['native'].')')
                    ->required($locale === 'en')
                    ->maxLength(255)
                    ->live(onBlur: true, condition: $locale === 'en')
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($locale): void {
                        if ($locale === 'en' && blank($get('value'))) {
                            $set('value', static::choiceValue($state));
                        }
                    }))->columnSpanFull(),
                TextInput::make('value')
                    ->label(__('packstub-form-builder::form-builder.editor.choice_value'))
                    ->helperText(__('packstub-form-builder::form-builder.editor.choice_value_hint'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true, condition: $live),
            ])
            ->hintAction(
                Action::make('pasteChoices')
                    ->label(__('packstub-form-builder::form-builder.editor.paste_choices'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->modalHeading(__('packstub-form-builder::form-builder.editor.paste_choices'))
                    ->modalSubmitActionLabel(__('packstub-form-builder::form-builder.editor.paste_choices_submit'))
                    ->modalWidth(Width::Large)
                    ->schema([
                        Textarea::make('lines')
                            ->label(__('packstub-form-builder::form-builder.editor.paste_choices_lines'))
                            ->helperText(__('packstub-form-builder::form-builder.editor.paste_choices_hint'))
                            ->placeholder("Yes | نعم\nNo | لا")
                            ->rows(8)
                            ->required(),
                    ])
                    ->action(function (array $data, Repeater $component): void {
                        $component->state(static::withPastedChoices((array) $component->getRawState(), (string) $data['lines']));
                        $component->callAfterStateUpdated();
                    }),
            )
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => Localized::value($state, 'label') ?? ($state['value'] ?? null))
            ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_choice'))
            ->required()
            ->minItems(1)
            ->columnSpanFull();
    }

    /**
     * The default is one of the choices as typed so far; a default saved earlier
     * that no longer matches one stays on offer, so saving never drops it.
     */
    public function defaultInput(): Component
    {
        return Select::make('default')
            ->label(__('packstub-form-builder::form-builder.editor.default'))
            ->options(function (Get $get, mixed $state): array {
                $options = collect((array) $get('choices'))
                    ->filter(fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null))
                    ->mapWithKeys(fn (array $row): array => [(string) $row['value'] => (string) (Localized::value($row, 'label') ?? $row['value'])])
                    ->all();

                if (is_scalar($state) && $state !== '' && ! isset($options[(string) $state])) {
                    $options[(string) $state] = (string) $state;
                }

                return $options;
            })
            ->placeholder(__('packstub-form-builder::form-builder.editor.no_default'))
            ->native(false);
    }

    /**
     * The choices with the pasted lines added: one per line, "English | عربي" (or a tab
     * between them, as copied from a spreadsheet). A line with only Arabic fills the
     * Arabic label. Lines whose value is already a choice are skipped, and the empty
     * row a new field starts with is dropped.
     *
     * @param  array<string, mixed>  $choices
     * @return array<string, mixed>
     */
    public static function withPastedChoices(array $choices, string $lines): array
    {
        $choices = array_filter($choices, fn (mixed $row): bool => is_array($row)
            && (filled($row['value'] ?? null) || filled(array_filter((array) ($row['label'] ?? [])))));

        $taken = array_flip(array_map(fn (array $row): string => (string) ($row['value'] ?? ''), $choices));

        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $parts = array_map('trim', preg_split('/\s*[|\t]\s*/u', trim($line), 2) ?: []);

            if (($parts[0] ?? '') === '') {
                continue;
            }

            [$en, $ar] = count($parts) === 2
                ? $parts
                : (preg_match('/\p{Arabic}/u', $parts[0]) && ! preg_match('/[A-Za-z]/', $parts[0]) ? ['', $parts[0]] : [$parts[0], '']);

            $value = static::choiceValue($en !== '' ? $en : $ar);

            if ($value === '' || isset($taken[$value])) {
                continue;
            }

            $taken[$value] = true;
            $choices[(string) Str::uuid()] = ['label' => ['en' => $en, 'ar' => $ar], 'value' => $value];
        }

        return $choices;
    }

    /**
     * "Yes, by car" → "yes_by_car"; a label with no Latin letters or digits keeps its own text.
     */
    public static function choiceValue(?string $label): string
    {
        $label = trim((string) $label);

        return Str::slug($label, '_') ?: Str::limit($label, 255, '');
    }

    /**
     * @return array<int, mixed>
     */
    protected function choiceRules(Field $field): array
    {
        $values = array_keys($field->choices());

        return $values === [] ? [] : [Rule::in($values)];
    }
}
