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
use RuntimeException;

/**
 * CCAvenue (Bank Muscat). The request is an AES-256-GCM encrypted query string that the
 * browser POSTs to the gateway; the answer comes back as one encrypted "encResp" field.
 * The gateway's hosts below are the generic Bank Muscat ones: set the endpoint override
 * in the settings when the merchant is given its own.
 */
class CcAvenueGateway implements Gateway
{
    private const SANDBOX_URL = 'https://mti.bankmuscat.com:6443/transaction.do?command=initiateTransaction';

    private const LIVE_URL = 'https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction';

    private const CIPHER = 'aes-256-gcm';

    private const CURRENCY = 'OMR';

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::CcAvenue;
    }

    public function isConfigured(): bool
    {
        return $this->merchantId() !== '' && $this->accessCode() !== '' && $this->workingKey() !== '';
    }

    public function isTestMode(): bool
    {
        return (bool) SiteSetting::get('ccavenue.test_mode', true);
    }

    private function merchantId(): string
    {
        return (string) SiteSetting::get('ccavenue.merchant_id', '');
    }

    private function accessCode(): string
    {
        return (string) SiteSetting::get('ccavenue.access_code', '');
    }

    private function workingKey(): string
    {
        return SecretSetting::get('ccavenue.working_key');
    }

    private function endpointUrl(): string
    {
        $custom = (string) SiteSetting::get('ccavenue.endpoint_url', '');

        return $custom ?: ($this->isTestMode() ? self::SANDBOX_URL : self::LIVE_URL);
    }

    // ── Encryption ───────────────────────────────────────────────────────────

    /** CCAvenue's documented scheme: a random 16-byte IV, output hex(iv) . hex(ciphertext . tag). */
    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(16);

        $cipherText = openssl_encrypt($plainText, self::CIPHER, $this->workingKey(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new RuntimeException('CCAvenue encryption failed: '.openssl_error_string());
        }

        return bin2hex($iv).bin2hex($cipherText.$tag);
    }

    /** The first 16 bytes are the IV, the last 16 the GCM auth tag, the middle the ciphertext. */
    public function decrypt(string $encryptedText): string
    {
        $binary = ctype_xdigit($encryptedText) && strlen($encryptedText) % 2 === 0 ? hex2bin($encryptedText) : false;

        if ($binary === false || strlen($binary) < 32) {
            throw new RuntimeException('CCAvenue decryption failed: invalid hex payload.');
        }

        $plainText = openssl_decrypt(
            substr($binary, 16, -16),
            self::CIPHER,
            $this->workingKey(),
            OPENSSL_RAW_DATA,
            substr($binary, 0, 16),
            substr($binary, -16),
        );

        if ($plainText === false) {
            throw new RuntimeException('CCAvenue decryption failed: '.openssl_error_string());
        }

        return $plainText;
    }

    // ── Payment initiation ───────────────────────────────────────────────────

    /**
     * order_id is the order number without its hyphen: Bank Muscat rejects anything that is
     * not alphanumeric ("Invalid Character. Error Code: 21000"). The same value is stored
     * on the order so the callback can find it again by what CCAvenue echoes back.
     */
    public function initiate(ServiceOrder $order): PaymentRedirect
    {
        $callback = route('payment.ccavenue.callback');
        $orderId = $order->compactNumber();
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

        $plain = [
            'merchant_id' => $this->merchantId(),
            'order_id' => $orderId,
            'amount' => number_format((float) $order->total, 3, '.', ''),
            'redirect_url' => $callback,
            'cancel_url' => $callback,
            'billing_name' => trim((string) ($order->customer_name ?: 'Guest')),
            'billing_address' => SiteSetting::siteName(),
            'billing_city' => 'Muscat',
            'billing_state' => 'Muscat',
            'billing_zip' => '100',
            'billing_country' => 'Oman',
            'billing_tel' => preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '00000000',
            'billing_email' => $order->customer_email ?: "noemail@{$host}",
            'delivery_name' => '',
            'delivery_address' => '',
            'delivery_city' => '',
            'delivery_state' => '',
            'delivery_zip' => '',
            'delivery_country' => '',
            'delivery_tel' => '',
            'language' => $order->locale === 'ar' ? 'AR' : 'EN',
            'currency' => self::CURRENCY,
            'tid' => (string) time(),
        ];

        $encRequest = $this->encrypt(http_build_query($plain));

        PaymentGatewayLog::log(
            $order,
            'ccavenue',
            'initiate_payment',
            ['plain' => array_merge($plain, ['billing_email' => '••••••••']), 'endpoint' => $this->endpointUrl()],
            ['encRequest_len' => strlen($encRequest)],
        );

        $order->update(['payment_session_id' => $orderId, 'payment_method' => PaymentGateway::CcAvenue]);
        PaymentGatewaySession::record($order, 'ccavenue', $orderId);

        return PaymentRedirect::post($this->endpointUrl(), ['encRequest' => $encRequest, 'access_code' => $this->accessCode()]);
    }
}
