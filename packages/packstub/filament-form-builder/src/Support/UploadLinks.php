<?php

namespace Packstub\FormBuilder\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Links to uploaded files. Uploads live on a private disk, so a file is reached through a signed
 * route that also checks who is asking; the path travels as an opaque token and is only ever
 * resolved inside the uploads directory.
 */
final class UploadLinks
{
    public const ROUTE = 'packstub-form-builder.file';

    /** A link to the file, or the stored path itself when the download route is switched off. */
    public static function url(string $path): string
    {
        if (! Route::has(self::ROUTE)) {
            return $path;
        }

        return URL::signedRoute(self::ROUTE, ['token' => self::encode($path)]);
    }

    public static function encode(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    /**
     * The stored path a token stands for, or null when it is not a file inside the uploads
     * directory (a tampered token, a "../" trick, a path elsewhere on the disk).
     */
    public static function resolve(string $token): ?string
    {
        $path = base64_decode(strtr($token, '-_', '+/'), true);

        if ($path === false || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        $directory = trim((string) config('packstub-form-builder.uploads.directory', 'form-uploads'), '/');

        return str_starts_with($path, $directory.'/') && ! str_ends_with($path, '/') ? $path : null;
    }
}
