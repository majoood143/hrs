<?php

namespace App\Support\MobileApp;

/** App version numbers ("1.2.0"): checked and compared number by number, "1.2" == "1.2.0". */
final class AppVersion
{
    public const PATTERN = '/^\d{1,4}(\.\d{1,4}){0,2}$/';

    public static function valid(?string $version): bool
    {
        return $version !== null && preg_match(self::PATTERN, $version) === 1;
    }

    /** -1, 0 or 1, like `<=>`. Both must be valid(). */
    public static function compare(string $a, string $b): int
    {
        $left = self::parts($a);
        $right = self::parts($b);

        return $left <=> $right;
    }

    public static function lessThan(string $a, string $b): bool
    {
        return self::compare($a, $b) < 0;
    }

    /** @return array{int, int, int} */
    private static function parts(string $version): array
    {
        $parts = array_map('intval', explode('.', $version));

        return [$parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0];
    }
}
