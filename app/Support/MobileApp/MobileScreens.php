<?php

namespace App\Support\MobileApp;

/**
 * The app screens an announcement's button can open, by key (the app maps each key to its route),
 * with the feature that screen belongs to. An announcement pointing at a feature that is off for
 * the asking app is not sent to it.
 */
final class MobileScreens
{
    /** screen key => feature key (null = always there) */
    public const SCREENS = [
        'home' => null,
        'horses_for_sale' => 'horses_for_sale',
        'tools_for_sale' => 'tools_for_sale',
        'transfer_board' => 'transfer_board',
        'farriers' => 'farriers',
        'stables' => 'stables',
        'clinics' => 'clinics',
        'centers' => 'centers',
        'shops' => 'shops',
        'events' => 'events',
        'blog' => 'blog',
        'videos' => 'videos',
        'post_horse' => 'post_horse',
        'post_tool' => 'post_tool',
        'post_farrier' => 'post_farrier',
        'post_transfer' => 'post_transfer',
        'racing' => 'racing',
        'silks' => 'silks',
        'bookings' => 'bookings',
        'my_bookings' => 'bookings',
        'my_stable' => 'stable_owner',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SCREENS);
    }

    public static function feature(string $screen): ?string
    {
        return self::SCREENS[$screen] ?? null;
    }
}
