<?php

/*
 * Laravel's TrustProxies (global middleware) reads this when the site sits behind a proxy: a load
 * balancer, nginx in front of PHP on another host, or Cloudflare. Without it every visitor seems to
 * come from the proxy's address, and the per-address limits (the API's rate limits, sign-in codes
 * per address) then count the whole public as one visitor.
 *
 * TRUSTED_PROXIES: the proxies' addresses or ranges, comma separated (e.g. Cloudflare's published
 * ranges), or "*" for whatever connects to PHP (only when nothing else can reach the server). Empty:
 * no proxy, the connecting address is the visitor's.
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
