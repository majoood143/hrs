<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGatewaySession;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Payments\Contracts\Gateway;
use App\Services\Payments\OrderPaymentService;
use App\Services\Payments\PaymentRedirect;
use App\Support\SecretSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thawani hosted checkout. A session is created for the order, the customer pays on
 * Thawani's page, and every path back (browser return, webhook, sweeps) settles the order by
 * asking Thawani for the session again, never by trusting what the request claims.
 */
class ThawaniGateway implements Gateway
{
    public function __construct(private readonly OrderPaymentService $payments) {}

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::Thawani;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '' && $this->publishableKey() !== '';
    }

    public function isTestMode(): bool
    {
        return (bool) SiteSetting::get('thawani.test_mode', true);
    }

    private function secretKey(): string
    {
        return SecretSetting::get('thawani.secret_key');
    }

    private function publishableKey(): string
    {
        return (string) SiteSetting::get('thawani.publishable_key', '');
    }

    private function baseUrl(): string
    {
        $override = rtrim((string) SiteSetting::get('thawani.base_url', ''), '/');

        return $override !== '' ? $override : ($this->isTestMode()
            ? 'https://uatcheckout.thawani.om/api/v1'
            : 'https://checkout.thawani.om/api/v1');
    }

    public function initiate(ServiceOrder $order): PaymentRedirect
    {
        // Thawani charges the sum of the product lines, so one line carries the whole total
        // (price, fee and VAT); the breakdown is on our own receipt. Product names are short.
        $name = Str::limit((string) ($order->service?->name ?? 'Service'), 28, '')." {$order->order_number}";

        $response = $this->createSession([
            'client_reference_id' => $order->order_number,
            'products' => [['name' => $name, 'quantity' => 1, 'unit_amount' => $order->totalBaisa()]],
            'success_url' => route('payment.thawani.return', ['reference' => $order->order_number]),
            'cancel_url' => route('payment.thawani.cancel', ['reference' => $order->order_number]),
            'metadata' => ['order_id' => (string) $order->getKey()],
        ], $order);

        $sessionId = $response['data']['session_id'] ?? null;

        if (! $sessionId) {
            throw new RuntimeException('Thawani did not return a payment session.');
        }

        $order->update(['payment_session_id' => $sessionId, 'payment_method' => PaymentGateway::Thawani]);
        PaymentGatewaySession::record($order, 'thawani', $sessionId);

        return PaymentRedirect::get($this->checkoutUrl($sessionId));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function createSession(array $params, ?ServiceOrder $order = null): array
    {
        $response = Http::withHeaders(['thawani-api-key' => $this->secretKey(), 'Content-Type' => 'application/json'])
            ->post("{$this->baseUrl()}/checkout/session", $params);

        PaymentGatewayLog::log($order, 'thawani', 'create_session', $params, $response->json() ?? ['raw' => $response->body()], $response->status());

        if (! $response->successful()) {
            Log::error('Thawani createSession failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Thawani payment gateway error: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function getSession(string $sessionId, ?ServiceOrder $order = null): array
    {
        $response = Http::withHeaders(['thawani-api-key' => $this->secretKey()])
            ->get("{$this->baseUrl()}/checkout/session/{$sessionId}");

        PaymentGatewayLog::log($order, 'thawani', 'get_session', ['session_id' => $sessionId], $response->json() ?? ['raw' => $response->body()], $response->status());

        if (! $response->successful()) {
            Log::error('Thawani getSession failed', ['session_id' => $sessionId, 'status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Thawani session fetch failed: '.$response->body());
        }

        return $response->json();
    }

    /** The hosted pay page lives on the bare checkout host, not under /api/v1. */
    public function checkoutUrl(string $sessionId): string
    {
        $host = preg_replace('#/api/v1/?$#', '', $this->baseUrl());

        return "{$host}/pay/{$sessionId}?key={$this->publishableKey()}";
    }

    /**
     * Ask Thawani about a session and settle the order against the answer. Checks the order's
     * *current* session by default, but a webhook names its own session id, which may belong to
     * an earlier attempt the order has since moved on from (a double-click, a back button, two
     * tabs); passing it explicitly makes sure the session the webhook is actually about is the
     * one checked, not whatever the order's column currently holds.
     *
     * @return string|null the session's payment_status ('paid' / 'unpaid' / 'cancelled'),
     *                     'amount_mismatch' when it says paid for a different amount, or
     *                     null when there is no session to check
     *
     * @throws RuntimeException
     */
    public function reconcile(ServiceOrder $order, ?string $sessionId = null): ?string
    {
        $sessionId ??= $order->payment_session_id;

        if (! $sessionId) {
            return null;
        }

        $data = $this->getSession($sessionId, $order)['data'] ?? [];
        $status = $data['payment_status'] ?? null;

        if ($status !== 'paid') {
            return $status;
        }

        // The session was created for this order's total, so a different amount means the
        // session is not the one we made: never mark the order paid on it.
        if (isset($data['total_amount']) && (int) $data['total_amount'] !== $order->totalBaisa()) {
            Log::warning('Thawani session paid for a different amount than the order total', [
                'order' => $order->order_number,
                'expected' => $order->totalBaisa(),
                'received' => $data['total_amount'],
            ]);
            PaymentGatewayLog::log($order, 'thawani', 'amount_mismatch', ['expected' => $order->totalBaisa()], ['outcome' => 'error', 'received' => $data['total_amount']]);

            return 'amount_mismatch';
        }

        // The session response carries no payment_ref; `invoice` is Thawani's reference for the payment.
        $this->payments->applyPaid($order, PaymentGateway::Thawani, $data['payment_ref'] ?? $data['invoice'] ?? null);

        return $status;
    }

    /**
     * Verify an incoming webhook's HMAC-SHA256 signature. With no secret configured the
     * webhook is open, which is safe only because reconcile() re-fetches the session itself.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = SecretSetting::get('thawani.webhook_secret');

        if ($secret === '') {
            return true;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
