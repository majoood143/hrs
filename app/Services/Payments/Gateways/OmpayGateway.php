<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGatewaySession;
use App\Models\ServiceOrder;
use App\Services\Payments\Concerns\UsesPaymentAccount;
use App\Services\Payments\Contracts\ChecksPayment;
use App\Services\Payments\Contracts\Gateway;
use App\Services\Payments\OrderPaymentService;
use App\Services\Payments\PaymentRedirect;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OMPAY bank-hosted checkout (https://developer.sandbox.truepay.ompay.om/docs): the customer enters
 * the card and OTP on OMPAY's page, which cannot be framed. It has no webhook and its return link
 * carries no status, so every path settles the order by asking OMPAY about the transactions we made.
 */
class OmpayGateway implements ChecksPayment, Gateway
{
    use UsesPaymentAccount;

    /** A retry makes a new transaction, and the customer may still pay an earlier one left open. */
    private const ATTEMPTS_CHECKED = 5;

    public function __construct(private readonly OrderPaymentService $payments) {}

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::Ompay;
    }

    public function isConfigured(): bool
    {
        return $this->secret('api_key') !== '' && $this->secret('api_secret') !== '';
    }

    public function isTestMode(): bool
    {
        return (bool) $this->setting('test_mode', true);
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://api.sandbox.truepay.ompay.om/api/v1'
            : 'https://api.truepay.ompay.om/api/v1';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> the answer's `data`
     *
     * @throws RuntimeException
     */
    private function call(string $path, string $event, array $params, ServiceOrder $order): array
    {
        /** @var Response $response */
        // a status inquiry is short (customers wait on it, up to ATTEMPTS_CHECKED in a row); starting a payment may take longer
        $response = Http::connectTimeout(3)->timeout($path === 'inquiry' ? 8 : 30)
            ->withHeaders(['OMPAY-API-Key' => $this->secret('api_key'), 'OMPAY-API-Secret' => $this->secret('api_secret')])
            ->post("{$this->baseUrl()}/transactions/{$path}", $params);

        PaymentGatewayLog::log($order, 'ompay', $event, $params, $response->json() ?? ['raw' => $response->body()], $response->status());

        if (! $response->successful()) {
            Log::error('OMPAY request failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('OMPAY error: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    public function initiate(ServiceOrder $order): PaymentRedirect
    {
        $data = $this->call('bank-hosted', 'create_session', [
            'amount' => $order->totalBaisa() / 1000,
            'currency' => 'OMR',
            'return_url' => route('payment.ompay.return', ['reference' => $order->order_number]),
            // OMPAY refuses a reference it has seen, and a retry on the same order is a new transaction
            'reference_number' => $order->order_number.'-'.Str::lower(Str::random(6)),
        ], $order);

        $transactionId = $data['transaction_id'] ?? null;

        if (! $transactionId || empty($data['redirect_url'])) {
            throw new RuntimeException('OMPAY did not return a payment page.');
        }

        $order->update(['payment_session_id' => $transactionId, 'payment_method' => PaymentGateway::Ompay]);
        PaymentGatewaySession::record($order, 'ompay', $transactionId);

        return PaymentRedirect::get($data['redirect_url']);
    }

    /**
     * Only ids we recorded for this order are asked about, never one from the return link, so a
     * customer cannot point an order at someone else's payment.
     *
     * @return string|null 'paid', 'amount_mismatch', 'unconfirmed' (may have been charged: never make it
     *                     look like a plain failure), or the latest attempt's 'processing' / 'pending' / 'failed'
     *
     * @throws RuntimeException
     */
    public function reconcile(ServiceOrder $order, ?string $sessionId = null): ?string
    {
        $ids = $sessionId ? [$sessionId] : PaymentGatewaySession::query()
            ->where('service_order_id', $order->getKey())
            ->where('gateway', 'ompay')
            ->latest('id')
            ->limit(self::ATTEMPTS_CHECKED)
            ->pluck('session_id')
            ->all();

        $latest = null;
        $unconfirmed = false;

        foreach ($ids as $id) {
            $data = $this->call('inquiry', 'get_session', ['transaction_id' => $id], $order);
            $status = strtolower((string) ($data['status'] ?? ''));

            if ($status === 'successful') {
                return $this->settlePaid($order, $data);
            }

            $latest ??= $status;
            $unconfirmed = $unconfirmed || ! empty($data['outcome_unconfirmed']);
        }

        return $unconfirmed ? 'unconfirmed' : $latest;
    }

    /** @param  array<string, mixed>  $data */
    private function settlePaid(ServiceOrder $order, array $data): string
    {
        $paid = (int) round((float) ($data['amount'] ?? 0) * 1000);

        // The transaction was created for this order's total, so a different amount means it is not ours.
        if ($paid !== $order->totalBaisa()) {
            Log::warning('OMPAY transaction paid for a different amount than the order total', ['order' => $order->order_number, 'expected' => $order->totalBaisa(), 'received' => $paid]);
            PaymentGatewayLog::log($order, 'ompay', 'amount_mismatch', ['expected' => $order->totalBaisa()], ['outcome' => 'error', 'received' => $paid]);

            return 'amount_mismatch';
        }

        // the transaction id is what OMPAY's refund and reverse calls ask for
        $this->payments->applyPaid($order, PaymentGateway::Ompay, $data['transaction_id'] ?? null);

        return 'paid';
    }
}
