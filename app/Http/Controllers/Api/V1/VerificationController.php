<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\CustomerOtpService;
use App\Services\Auth\OtpRequestResult;
use App\Support\Api\PostingLimit;
use App\Support\Api\PostingToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Phone verification for posting from the app (the website uses a captcha instead): a 6-digit
 * code by WhatsApp (SMS when WhatsApp cannot send it, or when asked for), the same codes and
 * limits as the customer sign-in (CustomerOtpService).
 */
class VerificationController extends Controller
{
    public function __construct(private readonly CustomerOtpService $otp) {}

    /**
     * Send a verification code
     *
     * 429 with `retry_after` (seconds) when asked too often; 503 when no channel can send.
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

        $result = $this->otp->request($data['phone'], $request->ip(), $data['channel'] ?? CustomerOtpService::CHANNEL_WHATSAPP, CustomerOtpService::PURPOSE_VERIFY);

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
     * Check a verification code
     *
     * Returns the `posting_token` to send with new posts (valid 12 hours, for this phone only).
     *
     * @unauthenticated
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $status = $this->otp->check($data['phone'], $data['code']);

        if ($status !== CustomerOtpService::VERIFIED) {
            throw ValidationException::withMessages([
                'code' => $status === CustomerOtpService::EXPIRED ? __('api.verification.expired') : __('api.verification.wrong'),
            ]);
        }

        $phone = (string) $this->otp->normalize($data['phone']);
        $token = PostingToken::issue($phone);

        return response()->json([
            'posting_token' => $token['token'],
            'expires_at' => $token['expires_at']->toIso8601String(),
            /** The contact number the posts will show. */
            'phone' => PostingLimit::contactNumber($phone),
        ]);
    }
}
