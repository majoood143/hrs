<?php

namespace App\Support\MobileApp;

use App\Models\SiteSetting;
use App\Support\PhoneNumber;

/**
 * Posting from the app (admin → Mobile app → Posting), stored in the `mobile.posting` site setting:
 * whether app posts wait for an admin's approval (status `pending`) and how many posts one verified
 * phone may publish in 24 hours. Off by default: posts go live at once, like the website's.
 * Also the app-review login: one phone number that takes a fixed code instead of a sent one, so the
 * App Store / Play reviewers can sign in and post without an Omani phone (empty = none).
 */
final class MobilePosting
{
    public const DEFAULT_DAILY_LIMIT = 5;

    public const MAX_DAILY_LIMIT = 50;

    /** The status of a new app post. */
    public static function newPostStatus(): string
    {
        return self::reviewed() ? 'pending' : 'active';
    }

    public static function reviewed(): bool
    {
        return self::settings()['review'];
    }

    public static function dailyLimit(): int
    {
        return self::settings()['daily_limit'];
    }

    /**
     * The reviewers' fixed code when this (normalized) phone is the app-review login, else null.
     */
    public static function reviewerCode(string $phone): ?string
    {
        $settings = self::settings();
        $code = (string) $settings['reviewer_code'];

        return preg_match('/^\d{6}$/', $code) === 1 && PhoneNumber::normalize($settings['reviewer_phone']) === $phone ? $code : null;
    }

    /** @return array{review: bool, daily_limit: int, reviewer_phone: ?string, reviewer_code: ?string} */
    public static function settings(): array
    {
        $stored = self::stored();
        $limit = (int) ($stored['daily_limit'] ?? self::DEFAULT_DAILY_LIMIT);

        return [
            'review' => (bool) ($stored['review'] ?? false),
            'daily_limit' => $limit >= 1 && $limit <= self::MAX_DAILY_LIMIT ? $limit : self::DEFAULT_DAILY_LIMIT,
            'reviewer_phone' => ($stored['reviewer_phone'] ?? null) ?: null,
            'reviewer_code' => ($stored['reviewer_code'] ?? null) ?: null,
        ];
    }

    /** @param  array<string, mixed>  $state  the admin form's `posting` state */
    public static function save(array $state): void
    {
        SiteSetting::set('mobile.posting', json_encode([
            'review' => (bool) ($state['review'] ?? false),
            'daily_limit' => (int) ($state['daily_limit'] ?? self::DEFAULT_DAILY_LIMIT),
            'reviewer_phone' => ($state['reviewer_phone'] ?? null) ?: null,
            'reviewer_code' => ($state['reviewer_code'] ?? null) ?: null,
        ]), 'text', null, 'mobile_app');
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        $value = SiteSetting::get('mobile.posting');
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
