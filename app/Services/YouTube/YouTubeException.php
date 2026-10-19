<?php

namespace App\Services\YouTube;

use RuntimeException;

/**
 * YouTube could not answer. `reason` is a key of admin_youtube.errors, `detail` a short safe value
 * (an HTTP status or the API's error reason): never the request URL, which carries the API key.
 */
class YouTubeException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly string $detail = '')
    {
        parent::__construct(trim($reason.' '.$detail));
    }

    /** The form kept in youtube_channels.last_error. */
    public function stored(): string
    {
        return $this->reason.'|'.$this->detail;
    }

    /** A stored error in the admin's language. */
    public static function label(?string $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        [$reason, $detail] = array_pad(explode('|', $stored, 2), 2, '');
        $key = 'admin_youtube.errors.'.$reason;

        return __(trans()->has($key) ? $key : 'admin_youtube.errors.unknown', ['detail' => $detail]);
    }
}
