<?php

namespace App\Support;

use App\Services\Stables\CreateStableBooking;
use Illuminate\Validation\Rule;

/**
 * The booking form's riders and payment fields, shared by the website (`bookings/slots/{slot}`) and
 * the app (`POST /api/v1/bookings/slots/{slot}`): which rider fields the stable asks for, their rules
 * and names in the error messages, and the riders as `CreateStableBooking` stores them.
 */
final class StableBookingForm
{
    public const MAX_RIDERS = 20;

    /** The rider fields in the order the form shows them (guardian = name + phone). */
    public const RIDER_FIELDS = ['age', 'level', 'weight', 'height', 'guardian', 'notes'];

    /**
     * Everything but the customer's phone (the website asks for it, the app has the signed-in one).
     *
     * @return array<string, mixed>
     */
    public static function rules(StableBookingSettings $settings, int $count, bool $free, bool $signedIn = false): array
    {
        $field = fn (string $name, array $rules) => $settings->showsRiderField($name)
            ? [$settings->requiresRiderField($name) ? 'required' : 'nullable', ...$rules]
            : ['prohibited'];

        return [
            'riders_count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_RIDERS],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'payment_option' => $free ? ['nullable'] : ['required', Rule::in([...$settings->paymentOptions(), ...($signedIn ? [CreateStableBooking::PACKAGE] : [])])],
            'package_purchase_id' => ['nullable', 'required_if:payment_option,package', 'integer'],
            'riders' => ['required', 'array', 'size:'.$count],
            'riders.*.name' => ['required', 'string', 'max:120'],
            'riders.*.age' => $field('age', ['integer', 'min:2', 'max:100']),
            'riders.*.level' => $field('level', [Rule::in(StableBookingSettings::LEVELS)]),
            'riders.*.weight' => $field('weight', ['numeric', 'min:10', 'max:250']),
            'riders.*.height' => $field('height', ['numeric', 'min:50', 'max:250']),
            'riders.*.guardian_name' => $field('guardian', ['string', 'max:120']),
            'riders.*.guardian_phone' => $field('guardian', ['string', 'max:20']),
            'riders.*.notes' => $field('notes', ['string', 'max:500']),
            'waiver' => $settings->showsRiderField('waiver') ? ['accepted'] : ['prohibited'],
        ];
    }

    /** "Rider 2: age" instead of "riders.1.age" in the error messages. */
    public static function attributeNames(int $count): array
    {
        $names = [];

        foreach (range(0, max(0, $count - 1)) as $i) {
            foreach (['name', 'age', 'level', 'weight', 'height', 'guardian_name', 'guardian_phone', 'notes'] as $field) {
                $names["riders.{$i}.{$field}"] = __('stable_bookings.form.rider_n', ['n' => $i + 1]).': '.__('stable_bookings.rider.'.$field);
            }
        }

        return $names;
    }

    /**
     * The validated riders as the booking stores them (empty values dropped, the waiver stamped).
     *
     * @param  array<string, mixed>  $data  the validated form
     * @return list<array<string, mixed>>
     */
    public static function riders(array $data, int $count, StableBookingSettings $settings): array
    {
        $riders = collect($data['riders'])->take($count)->map(fn (array $rider) => array_filter([
            'name' => trim($rider['name']),
            'age' => isset($rider['age']) && $rider['age'] !== '' ? (int) $rider['age'] : null,
            'level' => $rider['level'] ?? null,
            'weight' => isset($rider['weight']) && $rider['weight'] !== '' ? (float) $rider['weight'] : null,
            'height' => isset($rider['height']) && $rider['height'] !== '' ? (float) $rider['height'] : null,
            'guardian_name' => $rider['guardian_name'] ?? null,
            'guardian_phone' => PhoneNumber::normalize($rider['guardian_phone'] ?? null),
            'notes' => $rider['notes'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''))->values()->all();

        if ($settings->showsRiderField('waiver') && ($data['waiver'] ?? false)) {
            $riders = array_map(fn (array $rider) => $rider + ['waiver_accepted_at' => now()->toIso8601String()], $riders);
        }

        return $riders;
    }

    public static function riderCount(mixed $value): int
    {
        return max(1, min(self::MAX_RIDERS, (int) ($value ?: 1)));
    }
}
