<?php

namespace App\Support;

use App\Models\StablePaymentAccount;

/**
 * A stable's own booking rules, as its owner sets them (stored in stables.booking_settings). Every
 * reader goes through here so a missing or old value falls back to the same default.
 */
final class StableBookingSettings
{
    public const PAYMENT_OPTIONS = ['online', 'at_stable'];

    /** Who takes online payments: our merchant account, or the stable's own (approved) gateway account. */
    public const PAYMENT_MODES = ['platform', 'own'];

    public const ALERT_CHANNELS = ['email', 'sms', 'whatsapp'];

    /** Rider details a booking form can ask for (the rider's name is always asked). */
    public const RIDER_FIELDS = ['age', 'level', 'weight', 'height', 'guardian', 'notes', 'waiver'];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    /** @param  array<string, mixed>  $values */
    public function __construct(private readonly array $values = []) {}

    public static function from(?array $stored): self
    {
        return new self($stored ?? []);
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'horizon_days' => (int) config('stable_bookings.default_horizon_days', 30),
            'cancellation_hours' => 24,
            'payment_options' => ['online'],
            'payment_mode' => 'platform',
            'payment_gateway' => null,
            'alert_channels' => ['email'],
            'alert_email' => null,
            'alert_phone' => null,
            'staff_enabled' => false,
            'send_reminders' => true,
            'rider_fields' => [
                'age' => ['show' => true, 'required' => true],
                'level' => ['show' => true, 'required' => true],
                'weight' => ['show' => false, 'required' => false],
                'height' => ['show' => false, 'required' => false],
                'guardian' => ['show' => false, 'required' => false],
                'notes' => ['show' => true, 'required' => false],
                'waiver' => ['show' => false, 'required' => false],
            ],
            'waiver_text' => ['en' => null, 'ar' => null],
        ];
    }

    /** @return array<string, mixed> the stored values over the defaults, for the settings form */
    public function toArray(): array
    {
        return array_replace_recursive(self::defaults(), $this->values);
    }

    public function horizonDays(): int
    {
        $days = (int) ($this->values['horizon_days'] ?? self::defaults()['horizon_days']);

        return max(1, min($days, (int) config('stable_bookings.max_horizon_days', 120)));
    }

    /** Hours before the start a customer may still cancel online; null when they may not at all. */
    public function cancellationHours(): ?int
    {
        $hours = array_key_exists('cancellation_hours', $this->values) ? $this->values['cancellation_hours'] : 24;

        return $hours === null || $hours === '' ? null : max(0, (int) $hours);
    }

    /** @return list<string> */
    public function paymentOptions(): array
    {
        $options = array_values(array_intersect(self::PAYMENT_OPTIONS, (array) ($this->values['payment_options'] ?? ['online'])));

        return $options === [] ? ['online'] : $options;
    }

    public function paymentMode(): string
    {
        $mode = (string) ($this->values['payment_mode'] ?? 'platform');

        return in_array($mode, self::PAYMENT_MODES, true) ? $mode : 'platform';
    }

    /** The gateway of the stable's own account (thawani, nbo, ccavenue), when it takes payments itself. */
    public function paymentGateway(): ?string
    {
        $gateway = $this->values['payment_gateway'] ?? null;

        return in_array($gateway, StablePaymentAccount::gateways(), true) ? $gateway : null;
    }

    /** @return list<string> */
    public function alertChannels(): array
    {
        return array_values(array_intersect(self::ALERT_CHANNELS, (array) ($this->values['alert_channels'] ?? ['email'])));
    }

    public function alertEmail(): ?string
    {
        $email = trim((string) ($this->values['alert_email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function alertPhone(): ?string
    {
        return PhoneNumber::normalize($this->values['alert_phone'] ?? null);
    }

    /** SMS customers the day before their session (on unless the stable switched it off). */
    public function sendsReminders(): bool
    {
        return (bool) ($this->values['send_reminders'] ?? true);
    }

    public function staffEnabled(): bool
    {
        return (bool) ($this->values['staff_enabled'] ?? false);
    }

    public function showsRiderField(string $field): bool
    {
        return (bool) ($this->toArray()['rider_fields'][$field]['show'] ?? false);
    }

    /** A field is only required when it is also shown. */
    public function requiresRiderField(string $field): bool
    {
        return $this->showsRiderField($field) && (bool) ($this->toArray()['rider_fields'][$field]['required'] ?? false);
    }

    public function waiverText(?string $locale = null): ?string
    {
        $texts = (array) ($this->values['waiver_text'] ?? []);
        $locale ??= app()->getLocale();

        return filled($texts[$locale] ?? null) ? $texts[$locale] : (filled($texts['en'] ?? null) ? $texts['en'] : null);
    }
}
