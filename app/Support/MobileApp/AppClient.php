<?php

namespace App\Support\MobileApp;

use Illuminate\Http\Request;

/**
 * Which app is asking: the `X-App-Platform` (ios | android | web) and `X-App-Version` ("1.2.0")
 * headers the app sends with every request. A client that does not say (the web preview, curl) has
 * no platform/version: platform and version rules are then not applied to it, and it is never told
 * to update.
 */
final class AppClient
{
    public const PLATFORMS = ['ios', 'android'];

    public function __construct(
        public readonly ?string $platform,
        public readonly ?string $version,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $platform = strtolower(trim((string) $request->header('X-App-Platform')));
        $version = trim((string) $request->header('X-App-Version'));

        return new self(
            in_array($platform, self::PLATFORMS, true) ? $platform : null,
            AppVersion::valid($version) ? $version : null,
        );
    }

    /** @param  list<string>|null  $platforms  empty or null = every platform */
    public function onPlatform(?array $platforms): bool
    {
        return $this->platform === null || empty($platforms) || in_array($this->platform, $platforms, true);
    }

    /** At least $min (null = no minimum). An unknown version passes. */
    public function atLeast(?string $min): bool
    {
        return $this->version === null || ! AppVersion::valid($min) || ! AppVersion::lessThan($this->version, $min);
    }

    /** At most $max (null = no maximum). An unknown version passes. */
    public function atMost(?string $max): bool
    {
        return $this->version === null || ! AppVersion::valid($max) || AppVersion::compare($this->version, $max) <= 0;
    }
}
