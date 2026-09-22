<?php

namespace App\Services\Payments;

/**
 * Where to send the customer to pay: a plain redirect, or (CCAvenue) a POST the browser
 * must make to the gateway.
 */
final class PaymentRedirect
{
    /** @param  array<string, string>  $fields */
    private function __construct(
        public readonly string $url,
        public readonly array $fields = [],
        public readonly bool $post = false,
    ) {}

    public static function get(string $url): self
    {
        return new self($url);
    }

    /** @param  array<string, string>  $fields */
    public static function post(string $url, array $fields): self
    {
        return new self($url, $fields, true);
    }
}
