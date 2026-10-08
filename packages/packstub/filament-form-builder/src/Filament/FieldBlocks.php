<?php

namespace Packstub\FormBuilder\Filament;

use App\Filament\Support\TranslatableInput;
use App\Support\Localized;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Support\FieldConditions;

/**
 * The Builder field that edits a form's fields: one block per field type.
 * A block shows what nearly every field needs (label, required, width and the
 * type's essential settings); the rest folds under "More options".
 */
class FieldBlocks
{
    /** The order of the groups in the "Add field" menu; unknown groups come last. */
    public const GROUPS = ['basic', 'contact', 'choices', 'other', 'layout'];

    public static function make(string $name = 'fields'): Builder
    {
        return Builder::make($name)
            ->label(__('packstub-form-builder::form-builder.fields.fields'))
            ->hiddenLabel()
            ->blocks(static::orderedTypes()->map(fn (FieldType $type): Block => static::block($type))->values()->all())
            ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_field'))
            ->blockNumbers(false)
            ->blockIcons()
            ->blockPickerColumns(['default' => 1, 'md' => 2])
            ->blockPickerWidth(Width::ThreeExtraLarge)
            ->collapsible()
            ->cloneable()
            ->reorderableWithButtons()
            ->rule(static::uniqueKeysRule())
            ->rule(static::conditionsRule())
            ->columnSpanFull();
    }

    /**
     * The registered types in menu order: by group, then in the order they were registered.
     *
     * @return Collection<string, FieldType>
     */
    public static function orderedTypes(): Collection
    {
        $position = array_flip(static::GROUPS);
        $registered = app(FieldTypeRegistry::class)->all();
        $order = array_flip($registered->keys()->all());

        return $registered->sortBy(fn (FieldType $type, string $id): array => [
            $position[$type->group()] ?? count($position),
            $order[$id],
        ]);
    }

    public static function block(FieldType $type): Block
    {
        return Block::make($type::id())
            ->label(function (?array $state) use ($type): string|HtmlString {
                // No state: the block is being drawn in the "Add field" menu.
                if ($state === null) {
                    return static::pickerLabel($type);
                }

                return static::itemLabel($type, $state);
            })
            ->icon($type->icon())
            ->schema([
                Grid::make(2)->schema([
                    ...static::commonSchema($type),
                    ...($type->hasAdvancedSettingsOnly() ? [] : $type->editorSchema()),
                    ...static::conditionSchema($type),
                    ...static::moreOptions($type),
                ]),
            ]);
    }

    /**
     * The type's name with its one-line description under it. Inline styles: the
     * package's views are not scanned by the admin theme's Tailwind build.
     */
    protected static function pickerLabel(FieldType $type): string|HtmlString
    {
        $description = $type->description();

        if (blank($description)) {
            return $type->label();
        }

        return new HtmlString(
            '<span style="display:block;white-space:normal">'.e($type->label()).'</span>'
            .'<span style="display:block;white-space:normal;font-size:.75rem;line-height:1rem;font-weight:400;opacity:.65">'.e($description).'</span>'
        );
    }

    /**
     * A field's header in the builder: its label, its type, then badges for what is
     * otherwise only visible once the block is opened (required, half width, a
     * language with no label yet), so a folded form can be checked at a glance.
     *
     * @param  array<string, mixed>  $state
     */
    public static function itemLabel(FieldType $type, array $state): HtmlString
    {
        $label = Localized::value($state, 'label');
        $badges = [];

        if ($type->hasCommonSettings() && (bool) ($state['required'] ?? false)) {
            $badges[] = __('packstub-form-builder::form-builder.editor.badge_required');
        }

        if ($type->hasCommonSettings() && ($state['width'] ?? 'full') === 'half') {
            $badges[] = __('packstub-form-builder::form-builder.editor.width_half');
        }

        if (FieldConditions::rule($state) !== null) {
            $badges[] = __('packstub-form-builder::form-builder.editor.badge_conditional');
        }

        // a type with no label of its own (a paragraph) has nothing to translate
        if ($type::id() !== 'paragraph' && filled($label)) {
            foreach (array_keys(TranslatableInput::locales()) as $code) {
                if (blank(trim((string) ($state['label'][$code] ?? '')))) {
                    $badges[] = __('packstub-form-builder::form-builder.editor.badge_missing', ['language' => strtoupper($code)]);
                }
            }
        }

        $html = filled($label)
            ? e($label).' <span style="font-weight:400;opacity:.6">· '.e($type->label()).'</span>'
            : e($type->label());

        foreach ($badges as $badge) {
            $html .= ' <span style="display:inline-block;margin-inline-start:.25rem;padding:0 .4rem;border-radius:.375rem;font-size:.7rem;line-height:1.1rem;font-weight:500;background:color-mix(in srgb,currentColor 10%,transparent)">'.e($badge).'</span>';
        }

        return new HtmlString($html);
    }

    /**
     * What nearly every field needs: its label, and whether it is required and how wide.
     *
     * @return array<int, Component>
     */
    protected static function commonSchema(FieldType $type): array
    {
        $schema = [
            EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("label.{$locale}")
                ->label(__('packstub-form-builder::form-builder.editor.label').' ('.$meta['native'].')')
                // keys are always made from the English label: APP_LOCALE differs between servers,
                // and an Arabic label gives an empty key
                ->required($locale === 'en')
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($locale): void {
                    if ($locale === 'en' && blank($get('key'))) {
                        $set('key', Str::slug((string) $state, '_'));
                    }
                }))->columnSpanFull(),
        ];

        // A hidden field is nothing but its key and value, so its key stays in sight.
        if ($type->isInput() && ! $type->hasCommonSettings()) {
            $schema[] = static::keyInput();
        }

        if ($type->hasCommonSettings()) {
            $schema[] = Toggle::make('required')
                ->label(__('packstub-form-builder::form-builder.editor.required'))
                ->inline(false);

            $schema[] = Select::make('width')
                ->label(__('packstub-form-builder::form-builder.editor.width'))
                ->options([
                    'full' => __('packstub-form-builder::form-builder.editor.width_full'),
                    'half' => __('packstub-form-builder::form-builder.editor.width_half'),
                ])
                ->default('full')
                ->formatStateUsing(fn (?string $state): string => $state ?: 'full')
                ->native(false);
        }

        return $schema;
    }

    /**
     * The fine-tuning, folded away: help text, placeholder, default value, key, the
     * type's own settings when they are optional, and extra validation rules.
     *
     * @return array<int, Component>
     */
    protected static function moreOptions(FieldType $type): array
    {
        $schema = [];

        if ($type->hasCommonSettings()) {
            $schema[] = EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("hint.{$locale}")
                ->label(__('packstub-form-builder::form-builder.editor.hint').' ('.$meta['native'].')')
                ->maxLength(255))->columnSpanFull();

            if (! in_array($type::id(), ['checkbox', 'checkboxes', 'radio', 'conditional_radio', 'date'], true)) {
                $schema[] = EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("placeholder.{$locale}")
                    ->label(__('packstub-form-builder::form-builder.editor.placeholder').' ('.$meta['native'].')')
                    ->maxLength(255))->columnSpanFull();
            }

            if (! in_array($type::id(), ['checkbox', 'checkboxes'], true)) {
                $schema[] = $type->defaultInput();
            }

            if ($type->isInput()) {
                $schema[] = static::keyInput();
            }
        }

        if ($type->hasAdvancedSettingsOnly()) {
            array_push($schema, ...$type->editorSchema());
        }

        array_push($schema, ...static::rulesSchema($type));

        if ($schema === []) {
            return [];
        }

        return [
            Section::make(__('packstub-form-builder::form-builder.editor.more_options'))
                ->description(__('packstub-form-builder::form-builder.editor.more_options_hint'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->collapsed()
                ->compact()
                ->columns(2)
                ->columnSpanFull()
                ->schema($schema),
        ];
    }

    /**
     * "Show this field only when [an earlier field] [is / is not / is answered…] [value]".
     * The value is a list of the earlier field's choices when it has some ("condition.choice"),
     * else typed ("condition.value"); only the one in view is saved.
     *
     * @return array<int, Component>
     */
    protected static function conditionSchema(FieldType $type): array
    {
        if ($type::id() === 'hidden') {
            return [];
        }

        $source = fn (Get $get, Component $component, $livewire): ?array => static::sources($component, $livewire)[(string) $get('condition.field')] ?? null;
        $choices = fn (?array $source): array => $source === null ? [] : static::sourceChoices($source);
        $asksValue = fn (Get $get): bool => in_array($get('condition.operator') ?: 'is', FieldConditions::VALUE_OPERATORS, true);

        return [
            Section::make(__('packstub-form-builder::form-builder.editor.condition'))
                ->description(fn (Get $get, Component $component, $livewire): string => static::conditionSummary((array) ($get('condition') ?? []), static::sources($component, $livewire))
                    ?? __('packstub-form-builder::form-builder.editor.condition_hint'))
                ->icon('heroicon-o-eye')
                ->collapsed(fn (Get $get): bool => blank($get('condition.field')))
                ->compact()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('condition.field')
                        ->label(__('packstub-form-builder::form-builder.editor.condition_field'))
                        ->placeholder(__('packstub-form-builder::form-builder.editor.condition_always'))
                        ->options(fn (Component $component, $livewire): array => array_map(fn (array $source): string => $source['label'], static::sources($component, $livewire)))
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('condition.operator', 'is');
                            $set('condition.value', null);
                            $set('condition.choice', null);
                        }),
                    Select::make('condition.operator')
                        ->label(__('packstub-form-builder::form-builder.editor.condition_operator'))
                        ->options(fn (Get $get, Component $component, $livewire): array => static::operatorOptions($source($get, $component, $livewire)))
                        ->default('is')
                        ->selectablePlaceholder(false)
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => filled($get('condition.field'))),
                    Select::make('condition.choice')
                        ->label(__('packstub-form-builder::form-builder.editor.condition_value'))
                        ->options(fn (Get $get, Component $component, $livewire): array => $choices($source($get, $component, $livewire)))
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get, Component $component, $livewire): bool => filled($get('condition.field')) && $asksValue($get)
                            && $choices($source($get, $component, $livewire)) !== []),
                    TextInput::make('condition.value')
                        ->label(__('packstub-form-builder::form-builder.editor.condition_value'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get, Component $component, $livewire): bool => filled($get('condition.field')) && $asksValue($get)
                            && ($source($get, $component, $livewire)['type'] ?? null) !== 'checkbox'
                            && $choices($source($get, $component, $livewire)) === []),
                ]),
        ];
    }

    /**
     * The fields before this block's that a rule may depend on, keyed by field key.
     *
     * @return array<string, array{label: string, type: string, item: array<string, mixed>}>
     */
    protected static function sources(Component $component, mixed $livewire): array
    {
        $items = data_get($livewire, 'data.fields');

        if (! is_array($items) || ! preg_match('/\.fields\.([^.]+)\.data(\.|$)/', $component->getStatePath(), $match)) {
            return [];
        }

        return FieldConditions::sourcesBefore($items, $match[1]);
    }

    /**
     * @param  array{label: string, type: string, item: array<string, mixed>}  $source
     * @return array<string, string>
     */
    protected static function sourceChoices(array $source): array
    {
        $type = app(FieldTypeRegistry::class)->find($source['type']);

        if ($type === null || ! $type->hasChoices()) {
            return [];
        }

        return $type->fixedChoices() ?? collect((array) ($source['item']['data']['choices'] ?? []))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null))
            ->mapWithKeys(fn (array $row): array => [(string) $row['value'] => (string) (Localized::value($row, 'label') ?? $row['value'])])
            ->all();
    }

    /**
     * @param  array{label: string, type: string, item: array<string, mixed>}|null  $source
     * @return array<string, string>
     */
    protected static function operatorOptions(?array $source): array
    {
        $label = fn (string $key): string => __("packstub-form-builder::form-builder.editor.condition_operators.{$key}");

        // a tick box is ticked or not
        if (($source['type'] ?? null) === 'checkbox') {
            return ['filled' => $label('ticked'), 'empty' => $label('not_ticked')];
        }

        return collect(FieldConditions::OPERATORS)->mapWithKeys(fn (string $operator): array => [$operator => $label($operator)])->all();
    }

    /**
     * "Shown when “Travelled” is “Yes”", or null while there is no complete rule.
     *
     * @param  array<string, mixed>  $condition
     * @param  array<string, array{label: string, type: string, item: array<string, mixed>}>  $sources
     */
    protected static function conditionSummary(array $condition, array $sources): ?string
    {
        $rule = FieldConditions::rule(['condition' => $condition]);
        $source = $rule === null ? null : ($sources[$rule['field']] ?? null);

        if ($source === null) {
            return null;
        }

        $value = $rule['value'] === null ? '' : (static::sourceChoices($source)[$rule['value']] ?? $rule['value']);

        return __('packstub-form-builder::form-builder.editor.'.($value === '' ? 'condition_summary_answered' : 'condition_summary'), [
            'field' => $source['label'],
            'operator' => static::operatorOptions($source)[$rule['operator']] ?? $rule['operator'],
            'value' => $value,
        ]);
    }

    protected static function keyInput(): TextInput
    {
        return TextInput::make('key')
            ->label(__('packstub-form-builder::form-builder.editor.key'))
            ->helperText(__('packstub-form-builder::form-builder.editor.key_hint'))
            ->maxLength(64)
            ->regex('/^[a-z0-9_]*$/')
            ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state, '_'));
    }

    /**
     * @return array<int, Component>
     */
    protected static function rulesSchema(FieldType $type): array
    {
        // A conditional radio's value is structured: its details have their own length setting instead.
        if (! $type->isInput() || in_array($type::id(), ['hidden', 'conditional_radio'], true)) {
            return [];
        }

        return [
            TagsInput::make('rules')
                ->label(__('packstub-form-builder::form-builder.editor.rules'))
                ->helperText(__('packstub-form-builder::form-builder.editor.rules_hint'))
                ->placeholder('max:100')
                ->default([])
                ->columnSpanFull(),
        ];
    }

    /**
     * Every rule must depend on an input field placed before its own (moving or deleting that
     * field, or renaming its key, would otherwise leave a rule that can never be met).
     */
    protected static function conditionsRule(): \Closure
    {
        return fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            $items = (array) $value;

            foreach ($items as $itemKey => $item) {
                $rule = FieldConditions::rule(is_array($item['data'] ?? null) ? $item['data'] : []);

                if ($rule === null || array_key_exists($rule['field'], FieldConditions::sourcesBefore($items, (string) $itemKey))) {
                    continue;
                }

                $fail(__('packstub-form-builder::form-builder.editor.condition_broken', [
                    'label' => trim((string) Localized::value((array) $item['data'], 'label')) ?: (string) ($item['type'] ?? ''),
                    'field' => $rule['field'],
                ]));

                return;
            }
        };
    }

    protected static function uniqueKeysRule(): \Closure
    {
        return fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            $registry = app(FieldTypeRegistry::class);
            $seen = [];

            foreach ((array) $value as $item) {
                $type = $registry->find((string) ($item['type'] ?? ''));

                if ($type === null || ! $type->isInput()) {
                    continue;
                }

                $key = Str::slug((string) ($item['data']['key'] ?? ''), '_') ?: Str::slug(Field::keyLabel((array) ($item['data'] ?? [])), '_');

                if ($key !== '' && isset($seen[$key])) {
                    $fail(__('packstub-form-builder::form-builder.editor.duplicate_key', ['key' => $key]));

                    return;
                }

                $seen[$key] = true;
            }
        };
    }
}
