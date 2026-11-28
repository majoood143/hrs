<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\Stable;

/**
 * The stable desk (POS): a stable sells a session or a lesson package to someone standing at its
 * desk, or books for a customer who cannot book themselves. Three switches, all needed:
 * General Settings → Modules → POS (`module_pos_enabled`, off by default), the stable's own switch
 * (admin → the stable, `stables.pos_enabled`), and the app feature `stable_pos`.
 *
 * Payment methods: admins tick the ones the site allows (General Settings → Modules), each stable's
 * owner the ones its desk takes (the /stable panel's booking settings, `pos_methods`). `link` is a
 * payment link the customer pays online (`/pay/{order}`); the others are taken at the desk.
 */
final class StablePos
{
    public const METHODS = ['cash', 'card', 'bank_transfer', 'wallet', 'link'];

    /** Methods that may need a reference typed with them (a card slip, a transfer number). */
    public const WITH_REFERENCE = ['card', 'bank_transfer', 'wallet'];

    public const LINK = 'link';

    public static function enabled(): bool
    {
        return (bool) SiteSetting::get('module_pos_enabled', false);
    }

    /** Whether this stable's desk can sell now. */
    public static function openFor(Stable $stable): bool
    {
        return self::enabled() && $stable->pos_enabled && $stable->acceptsBookings();
    }

    /** @return list<string> the methods the site allows (every one until an admin unticks some) */
    public static function siteMethods(): array
    {
        $stored = SiteSetting::get('pos_payment_methods');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($decoded) ? array_values(array_intersect(self::METHODS, $decoded)) : self::METHODS;
    }

    /** @return list<string> the methods this stable's desk takes: its owner's choice within the site's */
    public static function methodsFor(Stable $stable): array
    {
        $chosen = $stable->booking_settings['pos_methods'] ?? null;

        return is_array($chosen)
            ? array_values(array_intersect(self::siteMethods(), $chosen))
            : self::siteMethods();
    }

    /** @return array<string, string> method => label, for forms */
    public static function options(?array $methods = null): array
    {
        return collect($methods ?? self::METHODS)->mapWithKeys(fn (string $m) => [$m => self::label($m)])->all();
    }

    public static function label(string $method): string
    {
        return __('stable_pos.methods.'.$method);
    }
}
