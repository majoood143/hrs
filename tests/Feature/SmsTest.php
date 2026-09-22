<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Services\Sms\Gateways\TamimahSmsGateway;
use App\Services\Sms\SmsManager;
use App\Support\SecretSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
    }

    private function gateway(): TamimahSmsGateway
    {
        return app(TamimahSmsGateway::class);
    }

    // ── The Tamimah request ──────────────────────────────────────────────────

    public function test_the_request_is_the_sendsms_operation_from_their_service_description(): void
    {
        $this->useTamimah(['sms.tamimah.app_id' => 'APP7', 'sms.tamimah.priority' => 2]);
        $this->tamimahAnswers();

        $this->gateway()->send('96891234567', 'Hello');

        Http::assertSent(function (Request $request) {
            $xml = simplexml_load_string($request->body());
            $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('https://www.tamimahsms.com/')->SendSMS;

            return $request->url() === self::TAMIMAH_URL
                && $request->method() === 'POST'
                && $request->header('SOAPAction') === ['"https://www.tamimahsms.com/SendSMS"']
                && str_starts_with($request->header('Content-Type')[0], 'text/xml')
                && (string) $body->UserName === 'hrs-user'
                && (string) $body->Password === 'hrs-pass'
                && (string) $body->Sender === 'HRS'
                && (string) $body->MSISDNs === '96891234567'
                && (string) $body->Message === 'Hello'
                && (string) $body->Priority === '2'
                && (string) $body->AppID === 'APP7'
                && (string) $body->Schdate === ''
                && (string) $body->SourceRef === '';
        });
    }

    public function test_arabic_and_xml_special_characters_survive_the_envelope(): void
    {
        $this->useTamimah(['sms.tamimah.password' => 'p&ss<w>"rd\'']);
        $message = 'رمزك هو 123456 & <b>"x"</b>';

        $xml = simplexml_load_string($this->gateway()->envelope('96891234567', $message));
        $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('https://www.tamimahsms.com/')->SendSMS;

        $this->assertSame($message, (string) $body->Message);
        $this->assertSame('p&ss<w>"rd\'', (string) $body->Password);
    }

    public function test_it_is_not_configured_until_credentials_and_a_sender_are_set(): void
    {
        $this->useSms('tamimah');
        $this->assertFalse($this->gateway()->isConfigured());
        $this->assertFalse($this->gateway()->send('96891234567', 'x')->ok);

        $this->useTamimah();
        $this->assertTrue($this->gateway()->isConfigured());
    }

    public function test_a_custom_service_address_is_used(): void
    {
        $this->useTamimah(['sms.tamimah.endpoint' => 'https://sms.example.om/bulk.asmx']);
        Http::fake(['sms.example.om/*' => Http::response($this->soapAnswer())]);

        $this->assertTrue($this->gateway()->send('96891234567', 'x')->ok);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sms.example.om/bulk.asmx');
    }

    // ── The Tamimah answer ───────────────────────────────────────────────────

    public function test_a_processed_message_is_sent_and_its_batch_reference_kept(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers(batch: 'BATCH-42');

        $result = $this->gateway()->send('96891234567', 'x');

        $this->assertTrue($result->ok);
        $this->assertSame('BATCH-42', $result->reference);
        $this->assertSame('200', $result->response['StatusCode']);
    }

    public function test_a_message_the_service_did_not_process_is_a_failure_with_its_reason(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers(processed: 0, code: '105', description: 'Invalid sender');

        $result = $this->gateway()->send('96891234567', 'x');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Invalid sender', $result->error);
    }

    public function test_success_codes_narrow_what_counts_as_sent(): void
    {
        $this->useTamimah(['sms.tamimah.success_codes' => '200, 201']);

        $this->tamimahAnswers(code: '201');
        $this->assertTrue($this->gateway()->send('96891234567', 'x')->ok);

        Http::swap(new Factory);
        $this->tamimahAnswers(code: '999', description: 'Queued for review');
        $result = $this->gateway()->send('96891234567', 'x');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('999', $result->error);
    }

    public function test_a_soap_fault_is_a_failure(): void
    {
        $this->useTamimah();
        Http::fake([self::TAMIMAH_URL => Http::response('<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultcode>soap:Server</faultcode><faultstring>Invalid credentials</faultstring></soap:Fault></soap:Body></soap:Envelope>', 500)]);

        $result = $this->gateway()->send('96891234567', 'x');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Invalid credentials', $result->error);
    }

    public function test_unreadable_answers_and_http_errors_are_failures(): void
    {
        $this->useTamimah();

        $this->assertFalse($this->gateway()->interpret(200, 'not xml at all')->ok);
        $this->assertFalse($this->gateway()->interpret(200, '<a><b>1</b></a>')->ok);
        $this->assertFalse($this->gateway()->interpret(503, $this->soapAnswer())->ok);
        $this->assertTrue($this->gateway()->interpret(200, $this->soapAnswer())->ok);
    }

    public function test_an_unreachable_service_is_a_failure_not_an_exception(): void
    {
        $this->useTamimah();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $result = $this->gateway()->send('96891234567', 'x');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('timeout', $result->error);
    }

    // ── The manager ──────────────────────────────────────────────────────────

    public function test_every_message_is_logged_with_its_outcome(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers(batch: 'B-9');
        $order = $this->makeOrder();

        $result = app(SmsManager::class)->send('96891234567', 'Your order is ready', 'order_completed', $order);

        $this->assertTrue($result->ok);
        $log = NotificationLog::firstOrFail();
        $this->assertSame('sms', $log->channel);
        $this->assertSame('order_completed', $log->type);
        $this->assertSame('96891234567', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertSame('Your order is ready', $log->message);
        $this->assertSame('B-9', $log->provider_reference);
        $this->assertSame($order->id, $log->service_order_id);
    }

    public function test_a_failure_is_logged_with_the_error_and_never_thrown(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers(processed: 0, description: 'Insufficient balance');

        $result = app(SmsManager::class)->send('96891234567', 'x', 'order_received');

        $this->assertFalse($result->ok);
        $log = NotificationLog::firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Insufficient balance', $log->error);
    }

    public function test_a_one_time_code_never_reaches_the_log(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers();

        app(SmsManager::class)->send('96891234567', 'code 482913', 'otp', sensitive: true);

        $log = NotificationLog::firstOrFail();
        $this->assertNull($log->message);
        $this->assertStringNotContainsString('482913', json_encode($log->toArray()));
    }

    public function test_without_a_driver_nothing_can_be_sent(): void
    {
        $sms = app(SmsManager::class);

        $this->assertNull($sms->driver());
        $this->assertFalse($sms->canSend());
        $this->assertFalse($sms->send('96891234567', 'x', 'test')->ok);
        $this->assertSame('failed', NotificationLog::firstOrFail()->status);

        $this->useSms('carrier-pigeon');
        $this->assertNull($sms->driver());
    }

    public function test_the_demo_driver_sends_nothing_but_says_so(): void
    {
        $this->useSms('demo');
        Http::fake();

        $sms = app(SmsManager::class);

        $this->assertTrue($sms->canSend());
        $this->assertTrue($sms->isDemo());
        $this->assertTrue($sms->send('96891234567', 'x', 'test')->ok);
        Http::assertNothingSent();
        $this->assertSame('demo', NotificationLog::firstOrFail()->provider_reference);
    }

    public function test_the_log_driver_writes_to_the_application_log(): void
    {
        $this->useSms('log');
        Log::shouldReceive('info')->once()->with('SMS (log driver)', ['to' => '96891234567', 'message' => 'hi']);

        $this->assertTrue(app(SmsManager::class)->send('96891234567', 'hi', 'test')->ok);
    }

    public function test_an_unconfigured_gateway_cannot_send(): void
    {
        $this->useSms('tamimah');

        $this->assertFalse(app(SmsManager::class)->canSend());
    }

    public function test_the_password_is_read_from_encrypted_storage_too(): void
    {
        $this->useTamimah(['sms.tamimah.password' => 'enc:'.Crypt::encryptString('secret-pass')]);

        $this->assertSame('secret-pass', SecretSetting::get('sms.tamimah.password'));
        $this->assertStringContainsString('secret-pass', $this->gateway()->envelope('96891234567', 'x'));
    }
}
