<?php

namespace Packstub\FormBuilder\Support;

use App\Filament\Support\TranslatableInput;

/**
 * Ready-made field sets offered by "Start from a template" on a new form. Every label is
 * filled in each configured language from the package's language files, so a template
 * arrives translated; the admin edits it like any other form.
 */
class FormTemplates
{
    public const KEYS = ['contact', 'service_application', 'booking_request', 'registration', 'feedback'];

    /**
     * @return array<string, string> key => name, in the admin's language
     */
    public static function options(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => __("packstub-form-builder::form-builder.templates.{$key}.name")])->all();
    }

    /**
     * @return array<string, string> key => one line on what it is for
     */
    public static function descriptions(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => __("packstub-form-builder::form-builder.templates.{$key}.description")])->all();
    }

    /**
     * The template's name in every language, for a new form's name.
     *
     * @return array<string, string>
     */
    public static function name(string $key): array
    {
        return static::text("templates.{$key}.name");
    }

    /**
     * The template's fields as builder items.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function fields(string $key): array
    {
        $half = ['width' => 'half'];
        $required = ['required' => true];

        return match ($key) {
            'contact' => [
                static::field('text', 'full_name', $required + $half),
                static::field('email', 'email', $required + $half),
                static::field('phone', 'phone', $half),
                static::field('text', 'subject', $half),
                static::field('textarea', 'message', $required),
            ],
            'service_application' => [
                static::field('heading', 'applicant', ['level' => 'h3']),
                static::field('text', 'full_name', $required),
                static::field('phone', 'phone', $required + $half),
                static::field('email', 'email', $required + $half),
                static::field('nationality', 'nationality', $half),
                static::field('text', 'id_number', $half),
                static::field('heading', 'request', ['level' => 'h3']),
                static::field('textarea', 'details', $required),
                static::field('file', 'attachment', ['accepted_types' => ['pdf', 'jpg', 'png'], 'max_size' => 5120]),
            ],
            'booking_request' => [
                static::field('text', 'full_name', $required),
                static::field('phone', 'phone', $required + $half),
                static::field('email', 'email', $half),
                static::field('date', 'preferred_date', $required + $half),
                static::field('select', 'preferred_time', $half + ['choices' => static::choices('preferred_time', ['morning', 'afternoon', 'evening'])]),
                static::field('number', 'people', $half + ['min' => 1, 'max' => 50, 'default' => 1]),
                static::field('textarea', 'notes'),
            ],
            'registration' => [
                static::field('text', 'full_name', $required),
                static::field('email', 'email', $required + $half),
                static::field('phone', 'phone', $required + $half),
                static::field('nationality', 'nationality', $half),
                static::field('date', 'date_of_birth', $half),
                static::field('radio', 'gender', ['choices' => static::choices('gender', ['male', 'female'])]),
                static::field('checkbox', 'terms', $required),
            ],
            'feedback' => [
                static::field('radio', 'rating', $required + ['choices' => static::choices('rating', ['1', '2', '3', '4', '5'])]),
                static::field('textarea', 'liked'),
                static::field('textarea', 'improve'),
                static::field('checkbox', 'contact_me'),
                static::field('email', 'contact_email', $required + ['condition' => ['field' => 'contact_me', 'operator' => 'filled']]),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{type: string, data: array<string, mixed>}
     */
    protected static function field(string $type, string $key, array $extra = []): array
    {
        return ['type' => $type, 'data' => ['key' => $key, 'label' => static::text("template_fields.{$key}")] + $extra];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: array<string, string>}>
     */
    protected static function choices(string $field, array $values): array
    {
        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => static::text("template_choices.{$field}.{$value}"),
        ], $values);
    }

    /**
     * @return array<string, string>
     */
    protected static function text(string $key): array
    {
        return collect(array_keys(TranslatableInput::locales()))
            ->mapWithKeys(fn (string $code): array => [$code => __("packstub-form-builder::form-builder.{$key}", [], $code)])
            ->all();
    }
}
