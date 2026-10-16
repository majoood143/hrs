<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Customer;
use App\Services\Auth\CustomerOtpService;
use App\Services\Auth\OtpRequestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Customer sign-in from the app: a phone number, then the 6-digit code sent to it (WhatsApp first,
 * else SMS), the same codes and limits as the website's sign-in. The answer is a bearer token for
 * the `customer_api` guard; a guest's earlier orders from that phone become theirs, as on the site.
 */
class CustomerAuthController extends Controller
{
    public const TOKEN_DAYS = 180;

    public function __construct(private readonly CustomerOtpService $otp) {}

    /**
     * Send a sign-in code
     *
     * The same answer for every valid number, whether or not it ever ordered anything. 429 with
     * `retry_after` (seconds) when asked too often; 503 when no channel can send.
     *
     * @unauthenticated
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            /** With country code; 8 digits are taken as Omani. @example 99887766 */
            'phone' => ['required', 'string', 'max:30'],
            /** Preferred channel; WhatsApp falls back to SMS. Defaults to whatsapp. */
            'channel' => ['nullable', Rule::in([CustomerOtpService::CHANNEL_WHATSAPP, CustomerOtpService::CHANNEL_SMS])],
        ]);

        $result = $this->otp->request($data['phone'], $request->ip(), $data['channel'] ?? CustomerOtpService::CHANNEL_WHATSAPP, CustomerOtpService::PURPOSE_SIGN_IN);

        return match ($result->status) {
            OtpRequestResult::INVALID_PHONE => throw ValidationException::withMessages(['phone' => __('api.verification.invalid_phone')]),
            OtpRequestResult::THROTTLED => response()->json([
                'message' => __('api.verification.throttled', ['seconds' => $result->retryAfter]),
                'retry_after' => $result->retryAfter,
            ], 429),
            OtpRequestResult::UNAVAILABLE => response()->json(['message' => __('api.verification.unavailable')], 503),
            default => response()->json([
                /** `whatsapp` or `sms`: say where to look. */
                'channel' => $result->channel,
                /** Seconds before another code may be asked for. */
                'resend_in' => $result->retryAfter,
                'expires_in' => CustomerOtpService::TTL_MINUTES * 60,
                /** Only when the site's SMS driver is "demo" (presentations): the code itself. */
                'demo_code' => $result->demoCode,
            ]),
        };
    }

    /**
     * Sign in with the code
     *
     * Returns the bearer `token` for the account endpoints (valid 180 days, until signed out).
     *
     * @unauthenticated
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:10'],
            /** Shown nowhere yet; helps tell sessions apart. @example iPhone */
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        [$status, $customer] = $this->otp->verify($data['phone'], $data['code']);

        if ($status !== CustomerOtpService::VERIFIED || ! $customer instanceof Customer) {
            throw ValidationException::withMessages([
                'code' => $status === CustomerOtpService::EXPIRED ? __('api.verification.expired') : __('api.verification.wrong'),
            ]);
        }

        $expires = now()->addDays(self::TOKEN_DAYS);
        $token = $customer->createToken($data['device_name'] ?? 'app', ['customer'], $expires);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(),
            'customer' => CustomerResource::make($customer),
        ]);
    }

    /** Sign out (this device's token only). */
    public function logout(Request $request): JsonResponse
    {
        $request->user('customer_api')?->currentAccessToken()?->delete();

        return response()->json(['message' => __('api.account.signed_out')]);
    }
}
