<?php

namespace Packstub\FormBuilder\Fields\Concerns;

use App\Filament\Support\TranslatableInput;
use App\Support\Localized;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Field;

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
     */
    protected function choicesRepeater(bool $live = false): Repeater
    {
        return Repeater::make('choices')
            ->label(__('packstub-form-builder::form-builder.editor.choices'))
            ->schema([
                TextInput::make('value')
                    ->label(__('packstub-form-builder::form-builder.editor.choice_value'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true, condition: $live),
                TranslatableInput::grid(fn (string $locale, array $meta): TextInput => TextInput::make("label.{$locale}")
                    ->label(__('packstub-form-builder::form-builder.editor.choice_label').' ('.$meta['native'].')')
                    ->required($locale === TranslatableInput::defaultLocale())
                    ->maxLength(255))->columnSpanFull(),
            ])
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => Localized::value($state, 'label') ?? ($state['value'] ?? null))
            ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_choice'))
            ->required()
            ->minItems(1)
            ->columnSpanFull();
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
