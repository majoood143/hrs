<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Models\SiteSetting;
use App\Services\Payments\Contracts\Gateway;
use App\Services\Payments\Gateways\CcAvenueGateway;
use App\Services\Payments\Gateways\DemoGateway;
use App\Services\Payments\Gateways\NboGateway;
use App\Services\Payments\Gateways\ThawaniGateway;

/**
 * The gateways a customer may pay with: ticked on the Payment Gateways settings page AND with
 * their credentials filled in.
 */
class PaymentGateways
{
    public function get(PaymentGateway $gateway): Gateway
    {
        return app(match ($gateway) {
            PaymentGateway::Thawani => ThawaniGateway::class,
            PaymentGateway::Nbo => NboGateway::class,
            PaymentGateway::CcAvenue => CcAvenueGateway::class,
            PaymentGateway::Demo => DemoGateway::class,
        });
    }

    /** @return list<PaymentGateway> the keys ticked in the settings, whether or not they are configured */
    public function selected(): array
    {
        $stored = SiteSetting::get('enabled_gateways');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return collect(is_array($decoded) ? $decoded : [])
            ->map(fn ($key) => PaymentGateway::tryFrom((string) $key))
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<Gateway> */
    public function available(): array
    {
        return collect($this->selected())
            ->map(fn (PaymentGateway $key) => $this->get($key))
            ->filter(fn (Gateway $gateway) => $gateway->isConfigured())
            ->values()
            ->all();
    }

    public function find(string $key): ?Gateway
    {
        return collect($this->available())->first(fn (Gateway $gateway) => $gateway->gateway()->value === $key);
    }
}
