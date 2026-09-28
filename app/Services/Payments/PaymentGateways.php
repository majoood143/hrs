<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Models\StablePaymentAccount;
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

    /**
     * The gateways this order may be paid with. An order that goes to a stable's own account
     * (stable_payment_account_id) can only use that account, and only while it is approved;
     * every other order uses the site's gateways.
     *
     * @return list<Gateway>
     */
    public function forOrder(ServiceOrder $order): array
    {
        if (! $order->stable_payment_account_id) {
            return $this->available();
        }

        $account = StablePaymentAccount::query()->find($order->stable_payment_account_id);

        if (! $account?->isApproved()) {
            return [];
        }

        $gateway = $this->get($account->gateway)->forAccount($account);

        return $gateway->isConfigured() ? [$gateway] : [];
    }

    public function findForOrder(ServiceOrder $order, string $key): ?Gateway
    {
        return collect($this->forOrder($order))->first(fn (Gateway $gateway) => $gateway->gateway()->value === $key);
    }

    /**
     * The gateway set up with the keys this order was paid with, for reading its answer (returns,
     * callbacks, webhooks, sweeps). Unlike forOrder() it does not care whether the account is
     * still approved: a payment already started must still be settled.
     *
     * @template T of Gateway
     *
     * @param  T  $gateway
     * @return T
     */
    public function bind(Gateway $gateway, ServiceOrder $order): Gateway
    {
        $account = $order->stable_payment_account_id
            ? StablePaymentAccount::query()->find($order->stable_payment_account_id)
            : null;

        return $gateway->forAccount($account);
    }
}
