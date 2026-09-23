<?php

namespace Packstub\FormBuilder\Fields\Types;

use App\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * A searchable dropdown of nationalities, sourced from the app's public
 * Country list (nationality_en / nationality_ar) rather than choices
 * entered in the builder.
 */
class NationalityField extends FieldType
{
    public static function id(): string
    {
        return 'nationality';
    }

    public function icon(): string
    {
        return 'heroicon-o-flag';
    }

    public function hasChoices(): bool
    {
        return true;
    }

    public function fixedChoices(): ?array
    {
        return static::choices();
    }

    public function rules(Field $field): array
    {
        $values = array_keys(static::choices());

        return $values === [] ? [] : [Rule::in($values)];
    }

    public function formComponent(Field $field): Component
    {
        return $this->configure(
            Select::make($field->key)->options(static::choices())->searchable()->native(false),
            $field,
        );
    }

    /**
     * The value => label list of nationalities, in the current locale.
     * Value and label are the same string: there is no separate machine
     * code, so the plain HTML renderer's search input can submit the
     * label as-is and still validate.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return Country::query()
            ->public()
            ->orderBy('order')
            ->pluck(app()->getLocale() === 'ar' ? 'nationality_ar' : 'nationality_en')
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }
}
