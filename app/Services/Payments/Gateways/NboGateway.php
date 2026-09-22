<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGatewaySession;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Payments\Contracts\Gateway;
use App\Services\Payments\PaymentRedirect;
use App\Support\SecretSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * NBO (National Bank of Oman) hosted payment page. The payment is created server-side with an
 * AES-256-CBC encrypted request; the callback is an unsigned form POST, so the amount is
 * the check that stands between a forged request and a "paid" order.
 */
class NboGateway implements Gateway
{
    private const IV = 'PGKEYENCDECIVSPC';

    private const SANDBOX_URL = 'https://unifiedpg.nbo.om/OLTPSTG/payment/hosted.htm';

    private const LIVE_URL = 'https://unifiedpg.nbo.om/OLTP/payment/hosted.htm';

    private const CIPHER = 'AES-256-CBC';

    private const CURRENCY = '512'; // OMR

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::Nbo;
    }

    public function isConfigured(): bool
    {
        return $this->tranportalId() !== '' && $this->tranportalPassword() !== '' && $this->resourceKey() !== '';
    }

    public function isTestMode(): bool
    {
        return (bool) SiteSetting::get('nbo.test_mode', true);
    }

    private function tranportalId(): string
    {
        return (string) SiteSetting::get('nbo.tranportal_id', '');
    }

    private function tranportalPassword(): string
    {
        return SecretSetting::get('nbo.tranportal_password');
    }

    private function resourceKey(): string
    {
        return SecretSetting::get('nbo.resource_key');
    }

    private function endpointUrl(): string
    {
        $custom = (string) SiteSetting::get('nbo.endpoint_url', '');

        return $custom ?: ($this->isTestMode() ? self::SANDBOX_URL : self::LIVE_URL);
    }

    // ── Encryption ───────────────────────────────────────────────────────────

    public function encrypt(array $data): string
    {
        $encrypted = openssl_encrypt(
            urlencode(json_encode([$data], JSON_UNESCAPED_UNICODE)),
            self::CIPHER,
            $this->resourceKey(),
            OPENSSL_RAW_DATA,
            self::IV,
        );

        if ($encrypted === false) {
            throw new RuntimeException('NBO encryption failed: '.openssl_error_string());
        }

        return strtoupper(bin2hex($encrypted));
    }

    public function decrypt(string $hexData): array
    {
        $binary = hex2bin(strtolower($hexData));

        $decrypted = $binary === false ? false : openssl_decrypt($binary, self::CIPHER, $this->resourceKey(), OPENSSL_RAW_DATA, self::IV);

        if ($decrypted === false) {
            throw new RuntimeException('NBO decryption failed: '.openssl_error_string());
        }

        $parsed = json_decode(urldecode($decrypted), true);

        // NBO wraps the payload in an array: [{ ... }]
        if (is_array($parsed) && isset($parsed[0])) {
            return $parsed[0];
        }

        return is_array($parsed) ? $parsed : [];
    }

    // ── Payment initiation ───────────────────────────────────────────────────

    public function initiate(ServiceOrder $order): PaymentRedirect
    {
        $responseUrl = route('payment.nbo.callback');

        $plain = [
            'id' => $this->tranportalId(),
            'password' => $this->tranportalPassword(),
            'action' => '1',
            'amt' => number_format((float) $order->total, 3, '.', ''),
            'currencycode' => self::CURRENCY,
            'langid' => $order->locale === 'ar' ? 'ar' : 'en',
            'trackId' => $this->trackId($order),
            'responseURL' => $responseUrl,
            'errorURL' => $responseUrl,
            'udf1' => $order->compactNumber(),
            'udf2' => preg_replace('/\D+/', '', (string) $order->customer_phone),
            'billingInfo' => $this->billingInfo($order),
        ];

        $request = [
            'id' => $this->tranportalId(),
            'trandata' => $this->encrypt($plain),
            'responseURL' => $responseUrl,
            'errorURL' => $responseUrl,
        ];

        $response = Http::asJson()->post($this->endpointUrl(), [$request]);

        $body = json_decode(trim($response->body()), true);
        $payload = is_array($body) ? ($body[0] ?? $body) : [];

        PaymentGatewayLog::log($order, 'nbo', 'initiate_payment', ['plain' => $plain, 'encrypted' => $request], $payload ?: ['raw' => $response->body()], $response->status());

        if (! $response->successful()) {
            Log::error('NBO initiate failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('NBO payment gateway error: '.$response->body());
        }

        if (($payload['status'] ?? '') !== '1') {
            Log::error('NBO initiate: gateway error', ['payload' => $payload]);

            throw new RuntimeException('NBO: '.($payload['errorText'] ?? $payload['error'] ?? 'Payment initiation failed'));
        }

        // result is "PaymentID:PaymentPageURL"
        $result = (string) ($payload['result'] ?? '');
        $colon = strpos($result, ':');

        if ($colon === false || $colon === 0) {
            Log::error('NBO initiate: unexpected result field', ['payload' => $payload]);

            throw new RuntimeException('NBO: unexpected response format.');
        }

        $paymentId = substr($result, 0, $colon);
        $paymentUrl = substr($result, $colon + 1);

        if ($paymentId === '' || $paymentUrl === '') {
            throw new RuntimeException('NBO: empty PaymentID or URL in response.');
        }

        $order->update(['payment_session_id' => $paymentId, 'payment_method' => PaymentGateway::Nbo]);
        PaymentGatewaySession::record($order, 'nbo', $paymentId);

        return PaymentRedirect::get($paymentUrl.'?PaymentID='.$paymentId);
    }

    // ── Callback ─────────────────────────────────────────────────────────────

    /**
     * NBO documents the callback as { paymentId, trandata (encrypted), error, errorText }, but
     * in production it posts a flat, unencrypted form with inconsistent casing ("paymentid",
     * "Error"), carrying the transaction fields directly. Keys are case-folded so both shapes
     * read the same.
     *
     * @return array<string, mixed>
     */
    public function normalizedInput(Request $request): array
    {
        $all = $request->all();

        if (empty($all)) {
            $raw = trim((string) $request->getContent());
            $decoded = $raw !== '' ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $all = (isset($decoded[0]) && is_array($decoded[0])) ? $decoded[0] : $decoded;
            }
        }

        return collect($all)->mapWithKeys(fn ($value, $key) => [strtolower((string) $key) => $value])->all();
    }

    /** The first non-empty value among the given (case-insensitive) keys. */
    public function pick(array $normalizedInput, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $normalizedInput[strtolower($key)] ?? null;

            if ($value !== null && $value !== '' && ! is_array($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * The transaction fields, whether they came as an encrypted "trandata" blob or as plain
     * fields; null when neither is present.
     *
     * @return array<string, mixed>|null
     */
    public function resolveTranData(array $input): ?array
    {
        if ($trandata = $this->pick($input, 'trandata')) {
            return $this->decrypt($trandata);
        }

        $result = $this->pick($input, 'result');

        if (! $result) {
            return null;
        }

        $data = [
            'result' => $result,
            'tranId' => $this->pick($input, 'tranid'),
            'ref' => $this->pick($input, 'ref'),
            'amt' => $this->pick($input, 'amt'),
            'authRespCode' => $this->pick($input, 'authrespcode'),
            'authCode' => $this->pick($input, 'authcode', 'auth'),
            'cardNo' => $this->pick($input, 'cardno'),
            'cardType' => $this->pick($input, 'cardtype'),
            'trackId' => $this->pick($input, 'trackid'),
            'respDateTime' => $this->pick($input, 'respdatetime', 'postdate'),
        ];

        foreach (range(1, 5) as $n) {
            $data["udf{$n}"] = $this->pick($input, "udf{$n}");
        }

        return $data;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * The guide asks for a numeric trackId, and a retry of the same order must not collide
     * ("IPAY0100114 - Duplicate Record"): the order id plus the current milliseconds.
     */
    private function trackId(ServiceOrder $order): string
    {
        return $order->getKey().substr((string) round(microtime(true) * 1000), -6);
    }

    /**
     * billingInfo is mandatory for CyberSource; without it the payment fails after the
     * redirect with "Invalid email id". Country must be ISO alpha-2 ("OM") and the phone
     * digits only, or the enrolment check fails ("IPAY0400015"). The address is not collected
     * anywhere, so static defaults satisfy the required-field validation.
     */
    private function billingInfo(ServiceOrder $order): array
    {
        $parts = explode(' ', trim((string) ($order->customer_name ?: 'Guest')), 2);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

        return [
            'firstName' => $parts[0] ?: 'Guest',
            'lastName' => $parts[1] ?? 'Guest',
            'country' => 'OM',
            'phoneNumber' => preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '00000000',
            'address' => SiteSetting::siteName(),
            'postalCode' => '100',
            'locality' => 'Muscat',
            'administrativeArea' => 'Muscat',
            'email' => $order->customer_email ?: "noemail@{$host}",
        ];
    }
}
