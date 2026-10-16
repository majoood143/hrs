<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The photos on the app's Services screen (admin → Mobile app → Services screen), stored in the
 * `mobile.images` site setting as paths on the public disk. Sent in `app-config.services_images`;
 * a card without a photo here uses the one bundled in the app.
 */
final class MobileImages
{
    /** The photo cards: the "Book a session" banner, Stables and Racing. */
    public const KEYS = ['bookings', 'stables', 'racing'];

    public const DIRECTORY = 'mobile-app/services';

    /** @return array<string, string|null> key => path on the public disk */
    public static function settings(): array
    {
        $stored = self::stored();

        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [
            $key => is_string($stored[$key] ?? null) && $stored[$key] !== '' ? $stored[$key] : null,
        ])->all();
    }

    /** @return array<string, string|null> key => absolute URL */
    public static function urls(): array
    {
        return array_map(
            fn (?string $path) => $path !== null ? Storage::disk('public')->url($path) : null,
            self::settings(),
        );
    }

    /**
     * @param  array<string, mixed>  $state  the admin form's `images` state
     */
    public static function save(array $state): void
    {
        $before = self::settings();
        $after = collect(self::KEYS)->mapWithKeys(function (string $key) use ($state) {
            $value = $state[$key] ?? null;
            // FileUpload's state is a path, or a one-item list of paths while it holds an upload.
            $path = is_array($value) ? (array_values($value)[0] ?? null) : $value;

            return [$key => is_string($path) && $path !== '' ? $path : null];
        })->all();

        SiteSetting::set('mobile.images', json_encode($after), 'text', null, 'mobile_app');

        // A replaced or removed photo is not used anywhere else: delete the file.
        foreach ($before as $key => $old) {
            if ($old !== null && $old !== $after[$key] && str_starts_with($old, self::DIRECTORY.'/')) {
                Storage::disk('public')->delete($old);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.images');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
