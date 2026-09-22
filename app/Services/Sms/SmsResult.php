<?php

namespace App\Services\Sms;

/** What an SMS gateway said about one message. */
final class SmsResult
{
    /** @param  array<string, mixed>  $response  the gateway's answer, for the log */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $reference = null,
        public readonly ?string $error = null,
        public readonly array $response = [],
    ) {}

    public static function failed(string $error, array $response = []): self
    {
        return new self(false, null, $error, $response);
    }
}
