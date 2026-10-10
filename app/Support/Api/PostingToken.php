<?php

namespace App\Support\Api;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Proof that a phone was verified (a code sent to it was typed back), for posting from the app
 * without an account: the phone and an expiry, encrypted with the app key. Nothing is stored; a
 * leaked token only lets someone post as that phone until it expires, within the daily limit.
 */
final class PostingToken
{
    public const TTL_HOURS = 12;

    /**
     * @return array{token: string, expires_at: Carbon}
     */
    public static function issue(string $phone): array
    {
        $expires = now()->addHours(self::TTL_HOURS);

        return [
            'token' => Crypt::encryptString(json_encode(['phone' => $phone, 'exp' => $expires->getTimestamp(), 'v' => 1])),
            'expires_at' => $expires,
        ];
    }

    /** The verified phone (digits with country code), or null when the token is bad or expired. */
    public static function phone(?string $token): ?string
    {
        if (blank($token)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || ! is_string($payload['phone'] ?? null) || (int) ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $payload['phone'];
    }
}
