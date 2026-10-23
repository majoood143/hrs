<?php

namespace Packstub\FormBuilder\Fields\Types;

use App\Support\PhoneNumber;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class PhoneField extends InputField
{
    public static function id(): string
    {
        return 'phone';
    }

    public function icon(): string
    {
        return 'heroicon-o-phone';
    }

    public function group(): string
    {
        return 'contact';
    }

    public function inputType(): string
    {
        return 'tel';
    }

    public function editorSchema(): array
    {
        return [];
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:50'];
    }

    /**
     * The same number however it is typed: with or without the country code, spaces or Arabic digits.
     */
    public function uniqueKey(mixed $value, Field $field): ?string
    {
        return is_scalar($value) ? PhoneNumber::normalize((string) $value) : null;
    }

    public function formComponent(Field $field): Component
    {
        /** @var TextInput $input */
        $input = parent::formComponent($field);

        return $input->tel()->maxLength(50);
    }
}
