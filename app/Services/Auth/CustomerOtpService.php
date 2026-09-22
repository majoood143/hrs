<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerOtp;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Sms\SmsManager;
use App\Support\PhoneNumber;

/**
 * Phone + SMS code sign-in for customers.
 *
 * A code is six digits, lives five minutes, allows five wrong tries and works once. Only a keyed
 * hash of it is stored. Asking for codes is limited per phone (one a minute, five an hour) and per
 * address (twenty an hour), counted from the database so it holds whatever the cache driver is.
 */
class CustomerOtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const MAX_PER_PHONE_PER_HOUR = 5;

    public const MAX_PER_IP_PER_HOUR = 20;

    public const VERIFIED = 'verified';

    public const WRONG = 'wrong';

    public const EXPIRED = 'expired';

    public function __construct(private readonly SmsManager $sms) {}

    /** The phone in the one spelling we store, or null when it cannot be a real number. */
    public function normalize(?string $raw): ?string
    {
        $phone = PhoneNumber::normalize($raw);

        return $phone !== null && strlen($phone) >= 10 && strlen($phone) <= 15 ? $phone : null;
    }

    public function request(?string $rawPhone, ?string $ip = null): OtpRequestResult
    {
        $phone = $this->normalize($rawPhone);

        if ($phone === null) {
            return new OtpRequestResult(OtpRequestResult::INVALID_PHONE);
        }

        if (! $this->sms->canSend()) {
            return new OtpRequestResult(OtpRequestResult::UNAVAILABLE);
        }

        if (($wait = $this->throttleSeconds($phone, $ip)) > 0) {
            return new OtpRequestResult(OtpRequestResult::THROTTLED, $wait);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // a newer code replaces any earlier one still waiting
        CustomerOtp::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        CustomerOtp::create([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $ip,
        ]);

        $result = $this->sms->send($phone, __('account.otp_sms', ['code' => $code, 'minutes' => self::TTL_MINUTES, 'site' => SiteSetting::siteName()]), 'otp', sensitive: true);

        if (! $result->ok) {
            // the customer cannot use a code they never got
            CustomerOtp::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return new OtpRequestResult(OtpRequestResult::UNAVAILABLE);
        }

        return new OtpRequestResult(OtpRequestResult::SENT, self::RESEND_SECONDS, $this->sms->isDemo() ? $code : null);
    }

    /**
     * Check a code. On success the code is used up and the customer (created on first sign-in) is
     * returned in the result's second element.
     *
     * @return array{0: string, 1: ?Customer} [VERIFIED|WRONG|EXPIRED, customer]
     */
    public function verify(?string $rawPhone, ?string $rawCode): array
    {
        $phone = $this->normalize($rawPhone);
        $code = preg_replace('/\D+/', '', (string) $rawCode);

        if ($phone === null || $code === '') {
            return [self::WRONG, null];
        }

        $otp = CustomerOtp::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return [self::EXPIRED, null];
        }

        // count the try before looking at it, so a guess is spent even if the request dies
        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, $this->hash($phone, $code))) {
            return [self::WRONG, null];
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return [self::VERIFIED, $this->customerFor($phone)];
    }

    /**
     * The customer for a verified phone, created on their first sign-in, with every order placed
     * from that number (as a guest) now theirs.
     */
    public function customerFor(string $phone): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => $phone], ['locale' => app()->getLocale()]);

        $orders = ServiceOrder::query()->where('customer_phone', $phone)->whereNull('customer_id');
        $latest = ServiceOrder::query()->where('customer_phone', $phone)->latest('id')->first();

        $customer->forceFill([
            'phone_verified_at' => $customer->phone_verified_at ?? now(),
            'name' => $customer->name ?: $latest?->customer_name,
            'email' => $customer->email ?: $latest?->customer_email,
            'last_login_at' => now(),
        ])->save();

        $orders->update(['customer_id' => $customer->getKey()]);

        return $customer;
    }

    /** Seconds until another code may be asked for, 0 when one may be asked for now. */
    private function throttleSeconds(string $phone, ?string $ip): int
    {
        $last = CustomerOtp::query()->where('phone', $phone)->latest('id')->first();

        if ($last && $last->created_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            return max(1, self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now(), true));
        }

        $hour = now()->subHour();
        $perPhone = CustomerOtp::query()->where('phone', $phone)->where('created_at', '>=', $hour)->count();
        $perIp = $ip ? CustomerOtp::query()->where('ip', $ip)->where('created_at', '>=', $hour)->count() : 0;

        if ($perPhone >= self::MAX_PER_PHONE_PER_HOUR || $perIp >= self::MAX_PER_IP_PER_HOUR) {
            $oldest = CustomerOtp::query()->where('phone', $phone)->where('created_at', '>=', $hour)->oldest('id')->first();

            return $oldest ? max(60, 3600 - (int) $oldest->created_at->diffInSeconds(now(), true)) : 3600;
        }

        return 0;
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key').'|'.$phone);
    }
}
