<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\SiteSetting;
use App\Support\SecretSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class GatewayLogAndSecretsTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite();
    }

    private function log(string $gateway, array $response): PaymentGatewayLog
    {
        return new PaymentGatewayLog(['gateway' => $gateway, 'event' => 'x', 'response_payload' => $response]);
    }

    public function test_each_gateways_answer_is_classified(): void
    {
        $cases = [
            ['thawani', ['data' => ['payment_status' => 'paid']], 'success'],
            ['thawani', ['data' => ['payment_status' => 'unpaid']], 'failed'],
            ['thawani', ['error' => 'x'], 'error'],
            ['thawani', ['data' => []], 'unknown'],
            ['nbo', ['result' => 'CAPTURED'], 'success'],
            ['nbo', ['result' => 'APPROVED'], 'success'],
            ['nbo', ['result' => 'NOT CAPTURED'], 'failed'],
            ['nbo', ['error' => 'IPAY01', 'errorText' => 'x'], 'failed'],
            ['nbo', ['error' => 'no_transaction_data'], 'error'],
            ['nbo', ['error' => '0', 'result' => 'CAPTURED'], 'success'],
            ['ccavenue', ['order_status' => 'Success'], 'success'],
            ['ccavenue', ['order_status' => 'Aborted'], 'failed'],
            ['ccavenue', ['order_status' => 'Initiated'], 'pending'],
            ['ccavenue', ['error' => 'x'], 'error'],
            ['demo', ['status' => 'approved'], 'success'],
            ['demo', ['status' => 'declined'], 'failed'],
            ['other', ['anything' => 1], 'unknown'],
            // our own events say so themselves
            ['thawani', ['outcome' => 'error', 'received' => 5], 'error'],
            ['nbo', ['outcome' => 'success'], 'success'],
        ];

        foreach ($cases as [$gateway, $response, $expected]) {
            $this->assertSame($expected, $this->log($gateway, $response)->outcome, $gateway.' '.json_encode($response));
        }
    }

    public function test_secrets_are_redacted_however_deep_they_sit(): void
    {
        $log = PaymentGatewayLog::log(null, 'nbo', 'initiate_payment', [
            'plain' => ['password' => 'hunter2', 'id' => 'TP1'],
            'secret_key' => 'sk',
            'ok' => 'visible',
        ], ['working_key' => 'wk']);

        $this->assertSame('••••••••', $log->request_payload['plain']['password']);
        $this->assertSame('TP1', $log->request_payload['plain']['id']);
        $this->assertSame('••••••••', $log->request_payload['secret_key']);
        $this->assertSame('visible', $log->request_payload['ok']);
        $this->assertSame('••••••••', $log->response_payload['working_key']);
    }

    public function test_log_persists_the_outcome_and_the_scope_filters_on_the_stored_column(): void
    {
        PaymentGatewayLog::log(null, 'thawani', 'get_session', [], ['data' => ['payment_status' => 'paid']]);
        PaymentGatewayLog::log(null, 'thawani', 'get_session', [], ['data' => ['payment_status' => 'unpaid']]);
        PaymentGatewayLog::log(null, 'nbo', 'callback', [], ['error' => 'no_transaction_data']);

        // Stored at write time, not derived on read: a fresh row fetched from the database
        // carries the classification without needing response_payload re-evaluated.
        $this->assertSame('success', DB::table('payment_gateway_logs')->where('gateway', 'thawani')->where('event', 'get_session')->orderBy('id')->value('outcome'));

        $this->assertSame(2, PaymentGatewayLog::query()->outcome('success')->count() + PaymentGatewayLog::query()->outcome('failed')->count());
        $this->assertSame(1, PaymentGatewayLog::query()->outcome('success')->count());
        $this->assertSame(1, PaymentGatewayLog::query()->outcome('failed')->count());
        $this->assertSame(1, PaymentGatewayLog::query()->outcome('error')->count());
    }

    public function test_a_log_can_exist_without_an_order(): void
    {
        $log = PaymentGatewayLog::log(null, 'ccavenue', 'callback', ['x' => 1], ['error' => 'y']);

        $this->assertNull($log->service_order_id);
        $this->assertNull($log->order);
    }

    public function test_secrets_are_stored_encrypted_and_read_back_plain(): void
    {
        Cache::forget('site_settings.all');
        SiteSetting::resetMemo();
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });

        SecretSetting::set('thawani.secret_key', 'sk_live_123', 'payment_gateways');

        $stored = DB::table('site_settings')->where('key', 'thawani.secret_key')->value('value');
        $this->assertStringStartsWith('enc:', $stored);
        $this->assertStringNotContainsString('sk_live_123', $stored);
        $this->assertSame('sk_live_123', SecretSetting::get('thawani.secret_key'));

        // a blank value clears it
        SecretSetting::set('thawani.secret_key', '', 'payment_gateways');
        $this->assertSame('', SecretSetting::get('thawani.secret_key'));
        $this->assertSame('fallback', SecretSetting::get('thawani.secret_key', 'fallback'));
    }

    public function test_a_plain_value_saved_before_encryption_still_reads(): void
    {
        $this->seedSiteSettings(['nbo.resource_key' => 'legacy-plain-key']);

        $this->assertSame('legacy-plain-key', SecretSetting::get('nbo.resource_key'));
        $this->assertSame('', SecretSetting::get('nbo.missing'));
    }

    public function test_a_value_encrypted_under_another_key_reads_as_unset(): void
    {
        $this->seedSiteSettings(['nbo.resource_key' => 'enc:'.base64_encode('garbage')]);

        $this->assertSame('', SecretSetting::get('nbo.resource_key'));
        $this->assertNull(SiteSetting::get('nbo.absent'));
    }
}
