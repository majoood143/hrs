<?php

namespace Tests\Feature\Api\V1;

use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/**
 * The API's own rate limits (AppServiceProvider): codes are limited per address and per phone, so
 * many phones behind one carrier address are not one bucket, and a proxy's address is not the
 * visitor's when the proxy is trusted (config/trustedproxy.php).
 */
class RateLimitsTest extends TestCase
{
    use PreparesMobileApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        (require database_path('migrations/2026_09_23_000001_create_customers_tables.php'))->up();
        $this->seedApiSettings(['sms.driver' => 'demo']);
    }

    public function test_one_phone_is_limited_whatever_the_address(): void
    {
        foreach (range(1, 10) as $i) {
            $this->fromAddress("10.0.0.{$i}")->postJson('/api/v1/auth/verify', ['phone' => '99887766', 'code' => '000000'])->assertUnprocessable();
        }

        $this->fromAddress('10.0.1.1')->postJson('/api/v1/auth/verify', ['phone' => '99887766', 'code' => '000000'])->assertStatus(429);
    }

    public function test_many_phones_behind_one_address_are_not_one_bucket(): void
    {
        foreach (range(1, 20) as $i) {
            $this->fromAddress('10.0.0.1')
                ->postJson('/api/v1/auth/verify', ['phone' => '9100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'code' => '000000'])
                ->assertUnprocessable();
        }
    }

    public function test_the_visitors_address_is_used_only_behind_a_trusted_proxy(): void
    {
        $this->fromAddress('10.0.0.1')->getJson('/api/v1/lookups', ['X-Forwarded-For' => '203.0.113.9']);
        $this->assertSame('10.0.0.1', app('request')->ip(), 'an untrusted proxy header is ignored');

        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->fromAddress('10.0.0.1')->getJson('/api/v1/lookups', ['X-Forwarded-For' => '203.0.113.9']);
        $this->assertSame('203.0.113.9', app('request')->ip());
    }

    /** The next request comes from this address. */
    private function fromAddress(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }
}
