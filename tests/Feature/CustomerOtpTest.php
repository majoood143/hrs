<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOtp;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Services\Auth\CustomerOtpService;
use App\Services\Auth\OtpRequestResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class CustomerOtpTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->useTamimah();
        $this->tamimahAnswers();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ask(string $phone = '9123 4567', string $ip = '10.0.0.1'): OtpRequestResult
    {
        return $this->otp()->request($phone, $ip);
    }

    // ── Asking for a code ────────────────────────────────────────────────────

    public function test_a_code_is_sent_by_sms_to_the_normalised_number(): void
    {
        $result = $this->ask('+968 9123 4567');

        $this->assertTrue($result->sent());
        $this->assertNull($result->demoCode, 'only the demo driver reveals the code');
        Http::assertSent(fn ($r) => str_contains($r->body(), '<MSISDNs>96891234567</MSISDNs>'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentCode());
    }

    public function test_the_code_is_stored_only_as_a_keyed_hash(): void
    {
        $this->ask();
        $code = $this->sentCode();

        $otp = CustomerOtp::firstOrFail();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertSame(64, strlen($otp->code_hash));
        $this->assertStringNotContainsString($code, json_encode($otp->toArray()));
        $this->assertSame('96891234567', $otp->phone);
        $this->assertTrue($otp->expires_at->between(now()->addMinutes(4), now()->addMinutes(5)->addSecond()));
    }

    public function test_the_sms_text_carries_the_code_the_site_and_the_lifetime_but_the_log_does_not(): void
    {
        $this->ask();

        $log = NotificationLog::firstOrFail();
        $this->assertSame('otp', $log->type);
        $this->assertNull($log->message);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'valid for 5 minutes'));
    }

    public function test_the_code_is_sent_in_the_visitors_language(): void
    {
        app()->setLocale('ar');
        $this->ask();

        Http::assertSent(fn ($r) => str_contains($r->body(), 'رمز الدخول'));
    }

    public function test_invalid_numbers_are_refused_without_sending_anything(): void
    {
        Http::fake();

        foreach ([null, '', 'abc', '12', '123456789012345678'] as $phone) {
            $this->assertSame(OtpRequestResult::INVALID_PHONE, $this->otp()->request($phone, '10.0.0.1')->status, (string) $phone);
        }

        Http::assertNothingSent();
        $this->assertSame(0, CustomerOtp::count());
    }

    public function test_without_a_working_sms_channel_no_code_is_issued(): void
    {
        $this->useSms('');
        Http::fake();

        $this->assertSame(OtpRequestResult::UNAVAILABLE, $this->ask()->status);
        $this->assertSame(0, CustomerOtp::count());
    }

    public function test_when_the_sms_fails_the_code_is_withdrawn(): void
    {
        $this->tamimahAnswers(processed: 0, description: 'Insufficient balance');

        $this->assertSame(OtpRequestResult::UNAVAILABLE, $this->ask()->status);

        $this->assertNotNull(CustomerOtp::firstOrFail()->consumed_at, 'a code nobody received cannot be used');
        $this->assertSame('failed', NotificationLog::firstOrFail()->status);
    }

    public function test_the_demo_driver_hands_back_the_code_and_it_works(): void
    {
        $this->useSms('demo');

        $result = $this->ask();

        $this->assertTrue($result->sent());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result->demoCode);
        $this->assertSame(CustomerOtpService::VERIFIED, $this->otp()->verify('96891234567', $result->demoCode)[0]);
    }

    // ── Rate limits ──────────────────────────────────────────────────────────

    public function test_a_second_code_within_a_minute_is_refused_with_the_wait(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');
        $this->ask();

        Carbon::setTestNow('2026-09-23 10:00:20');
        $result = $this->ask();

        $this->assertSame(OtpRequestResult::THROTTLED, $result->status);
        $this->assertSame(40, $result->retryAfter);
        $this->assertSame(1, CustomerOtp::count());

        Carbon::setTestNow('2026-09-23 10:01:05');
        $this->assertTrue($this->ask()->sent());
    }

    public function test_only_five_codes_an_hour_per_phone(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        foreach (range(1, 5) as $i) {
            $this->assertTrue($this->ask()->sent(), "code {$i}");
            Carbon::setTestNow(now()->addSeconds(70));
        }

        $this->assertSame(OtpRequestResult::THROTTLED, $this->ask()->status);

        Carbon::setTestNow('2026-09-23 11:10:00');
        $this->assertTrue($this->ask()->sent(), 'an hour later it is fine again');
    }

    public function test_only_twenty_codes_an_hour_per_address(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        foreach (range(1, 20) as $i) {
            $this->assertTrue($this->ask('9100 '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), '10.9.9.9')->sent(), "phone {$i}");
        }

        $this->assertSame(OtpRequestResult::THROTTLED, $this->ask('9200 0001', '10.9.9.9')->status);
        $this->assertTrue($this->ask('9200 0001', '10.8.8.8')->sent(), 'another address is unaffected');
    }

    public function test_the_limits_do_not_depend_on_the_cache(): void
    {
        $this->ask();
        Cache::flush();
        $this->seedSiteSettings(['sms.driver' => 'tamimah', 'sms.tamimah.username' => 'u', 'sms.tamimah.password' => 'p', 'sms.tamimah.sender' => 'S']);

        $this->assertSame(OtpRequestResult::THROTTLED, $this->ask()->status);
    }

    // ── Checking a code ──────────────────────────────────────────────────────

    public function test_the_right_code_signs_in_and_is_then_used_up(): void
    {
        $this->ask();
        $code = $this->sentCode();

        [$outcome, $customer] = $this->otp()->verify('9123 4567', $code);

        $this->assertSame(CustomerOtpService::VERIFIED, $outcome);
        $this->assertSame('96891234567', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertNotNull($customer->last_login_at);

        $this->assertSame(CustomerOtpService::EXPIRED, $this->otp()->verify('9123 4567', $code)[0], 'a code works once');
    }

    public function test_the_phone_and_code_may_be_typed_loosely(): void
    {
        $this->ask();
        $code = $this->sentCode();

        $this->assertSame(CustomerOtpService::VERIFIED, $this->otp()->verify('+968-9123-4567', substr($code, 0, 3).' '.substr($code, 3))[0]);
    }

    public function test_a_wrong_code_is_refused_and_counts_as_a_try(): void
    {
        $this->ask();
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->assertSame(CustomerOtpService::WRONG, $this->otp()->verify('96891234567', $wrong)[0]);
        $this->assertSame(1, CustomerOtp::firstOrFail()->attempts);
        $this->assertSame(0, Customer::count());
    }

    public function test_five_wrong_tries_lock_the_code_even_against_the_right_answer(): void
    {
        $this->ask();
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $ignored) {
            $this->assertSame(CustomerOtpService::WRONG, $this->otp()->verify('96891234567', $wrong)[0]);
        }

        $this->assertSame(CustomerOtpService::EXPIRED, $this->otp()->verify('96891234567', $code)[0]);
        $this->assertSame(0, Customer::count());
    }

    public function test_a_code_expires_after_five_minutes(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');
        $this->ask();
        $code = $this->sentCode();

        Carbon::setTestNow('2026-09-23 10:05:01');

        $this->assertSame(CustomerOtpService::EXPIRED, $this->otp()->verify('96891234567', $code)[0]);
    }

    public function test_a_newer_code_replaces_the_older_one(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');
        $this->ask();
        $first = $this->sentCode();

        Carbon::setTestNow('2026-09-23 10:02:00');
        $this->ask();
        $second = $this->sentCode();

        if ($first !== $second) {
            $this->assertSame(CustomerOtpService::WRONG, $this->otp()->verify('96891234567', $first)[0], 'the first is no longer the live code');
        }
        $this->assertSame(CustomerOtpService::VERIFIED, $this->otp()->verify('96891234567', $second)[0]);
    }

    public function test_a_code_for_one_phone_does_not_open_another(): void
    {
        $this->ask('9123 4567');
        $code = $this->sentCode();

        $this->assertSame(CustomerOtpService::EXPIRED, $this->otp()->verify('9555 5555', $code)[0]);
        $this->assertSame(0, Customer::count());
    }

    public function test_garbage_input_is_just_wrong(): void
    {
        $this->ask();

        foreach ([[null, null], ['96891234567', ''], ['96891234567', 'abcdef'], ['x', '123456']] as [$phone, $code]) {
            $this->assertSame(CustomerOtpService::WRONG, $this->otp()->verify($phone, $code)[0]);
        }
    }

    // ── Who the customer is ──────────────────────────────────────────────────

    public function test_signing_in_attaches_the_orders_placed_as_a_guest_from_that_number(): void
    {
        $mine = $this->makeOrder(10, 5.0, ['phone' => '9123 4567', 'name' => 'Ali Al Balushi', 'email' => 'ali@example.com']);
        $mine2 = $this->makeOrder(10, null, ['phone' => '96891234567']);
        $someoneElses = $this->makeOrder(10, null, ['phone' => '96899999999', 'name' => 'Other']);
        $this->assertNull($mine->customer_id);

        $this->ask();
        [, $customer] = $this->otp()->verify('96891234567', $this->sentCode());

        $this->assertSame($customer->id, $mine->refresh()->customer_id);
        $this->assertSame($customer->id, $mine2->refresh()->customer_id);
        $this->assertNull($someoneElses->refresh()->customer_id);
        $this->assertSame('Ali Al Balushi', $customer->name, 'taken from their latest order');
        $this->assertSame(2, $customer->orders()->count());
    }

    public function test_the_same_person_is_one_customer_however_they_type_the_number(): void
    {
        foreach (['9123 4567', '+96891234567', '0096891234567'] as $spelling) {
            Carbon::setTestNow(now()->addMinutes(2));
            $this->ask($spelling);
            $this->otp()->verify($spelling, $this->sentCode());
        }

        $this->assertSame(1, Customer::count());
    }

    public function test_a_returning_customer_keeps_their_details(): void
    {
        $existing = $this->makeCustomer('96891234567', ['name' => 'Chosen Name', 'email' => 'me@example.com']);
        $this->makeOrder(10, null, ['phone' => '96891234567', 'name' => 'Different Name']);

        $this->ask();
        [, $customer] = $this->otp()->verify('96891234567', $this->sentCode());

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame('Chosen Name', $customer->name);
        $this->assertSame(1, ServiceOrder::whereNotNull('customer_id')->count());
    }

    public function test_the_phone_is_masked_for_display(): void
    {
        $this->assertSame('+968 ••••4567', Customer::mask('96891234567'));
        $this->assertSame('••••4567', Customer::mask('91234567'));
        $this->assertSame('+968 ••••4567', $this->makeCustomer()->maskedPhone());
        $this->assertSame('Ali Al Balushi', $this->makeCustomer('96800000001')->displayName());
        $this->assertSame('+968 ••••0002', $this->makeCustomer('96800000002', ['name' => null])->displayName());
    }

    public function test_old_code_rows_are_pruned_after_a_day(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->ask();
        Carbon::setTestNow('2026-09-23 10:00:00');
        $this->ask('9555 5555');

        $this->artisan('model:prune', ['--model' => [CustomerOtp::class]])->assertSuccessful();

        $this->assertSame(1, CustomerOtp::count());
        $this->assertSame('96895555555', CustomerOtp::firstOrFail()->phone);
    }
}
