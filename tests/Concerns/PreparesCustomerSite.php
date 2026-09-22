<?php

namespace Tests\Concerns;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Services\Auth\CustomerOtpService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The customer tables (from the real migrations) on top of the order site, and helpers to pick an SMS
 * driver and to read the code that "was sent".
 */
trait PreparesCustomerSite
{
    use MakesOrderForms;

    protected const TAMIMAH_URL = 'https://tamimahsms.com/user/bulkpush.asmx';

    protected function prepareCustomerSite(array $settings = []): void
    {
        $this->prepareFormSite($settings);

        (require database_path('migrations/2026_09_23_000001_create_customers_tables.php'))->up();
    }

    /** @param  array<string, mixed>  $extra */
    protected function useSms(string $driver, array $extra = []): void
    {
        $current = Cache::get('site_settings.all', collect());

        $settings = $current->map(fn ($setting) => $setting->type === 'boolean' ? $setting->value === 'true' : $setting->value)->all();

        $this->seedSiteSettings($extra + ['sms.driver' => $driver] + $settings);
    }

    protected function useTamimah(array $extra = []): void
    {
        $this->useSms('tamimah', $extra + [
            'sms.tamimah.username' => 'hrs-user',
            'sms.tamimah.password' => 'hrs-pass',
            'sms.tamimah.sender' => 'HRS',
        ]);
    }

    protected function tamimahAnswers(int $processed = 1, string $code = '200', string $description = 'Message accepted', string $batch = 'B-100', int $status = 200): void
    {
        // fakes stack (the first stub wins), so start from a clean factory
        Http::swap(new Factory);
        Http::fake([self::TAMIMAH_URL => Http::response($this->soapAnswer($processed, $code, $description, $batch), $status)]);
    }

    protected function soapAnswer(int $processed = 1, string $code = '200', string $description = 'Message accepted', string $batch = 'B-100'): string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SendSMSResponse xmlns="https://www.tamimahsms.com/">
      <SendSMSResult>
        <Proccessed>{$processed}</Proccessed>
        <StatusCode>{$code}</StatusCode>
        <BatchRefCode>{$batch}</BatchRefCode>
        <StatusDesc>{$description}</StatusDesc>
      </SendSMSResult>
    </SendSMSResponse>
  </soap:Body>
</soap:Envelope>
XML;
    }

    /** The six-digit code in the last SMS sent through the Tamimah fake. */
    protected function sentCode(): string
    {
        $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->body())->filter(fn ($body) => str_contains($body, '<Message>'))->values();

        preg_match('#<Message>(.*?)</Message>#s', (string) $bodies->last(), $message);
        preg_match('/\b(\d{6})\b/', html_entity_decode((string) ($message[1] ?? '')), $code);

        return $code[1] ?? throw new \RuntimeException('No code was sent.');
    }

    protected function makeCustomer(string $phone = '96891234567', array $attributes = []): Customer
    {
        return Customer::create($attributes + ['phone' => $phone, 'name' => 'Ali Al Balushi', 'phone_verified_at' => now()]);
    }

    protected function paidOrderFor(Customer $customer, float $price = 10.0): ServiceOrder
    {
        $order = $this->makeOrder($price, 5.0, ['phone' => $customer->phone, 'name' => $customer->name]);
        $order->update([
            'customer_id' => $customer->id,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentGateway::Demo,
            'payment_reference' => 'DEMO-REF',
            'status' => OrderStatus::New,
            'receipt_number' => 'RC-'.now()->format('Y').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'paid_at' => now(),
        ]);

        return $order->refresh();
    }

    protected function otp(): CustomerOtpService
    {
        return app(CustomerOtpService::class);
    }
}
