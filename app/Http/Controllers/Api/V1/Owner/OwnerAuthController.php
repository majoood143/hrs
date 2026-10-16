<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Owner\OwnerResource;
use App\Models\User;
use App\Services\Auth\CustomerOtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Stable owners sign in to the app like they sign in to the website's /stable panel: phone + code
 * (the code comes from `owner/auth/request`, the customer sign-in's sender) or email + password.
 * Only users of type stable_owner; the answer is a bearer token with the `stable_owner` ability,
 * which opens /api/v1/owner/* and nothing else.
 */
class OwnerAuthController extends Controller
{
    public const ABILITY = 'stable_owner';

    public const TOKEN_DAYS = 90;

    public function __construct(private readonly CustomerOtpService $otp) {}

    /**
     * Sign in with a phone code
     *
     * The phone the owner confirmed when registering their stable. 422 on `code` when the code is
     * wrong or expired, on `phone` when no stable owner has that number.
     *
     * @unauthenticated
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:10'],
            /** @example iPhone */
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $status = $this->otp->check($data['phone'], $data['code']);

        if ($status !== CustomerOtpService::VERIFIED) {
            throw ValidationException::withMessages([
                'code' => $status === CustomerOtpService::EXPIRED ? __('api.verification.expired') : __('api.verification.wrong'),
            ]);
        }

        $user = User::query()
            ->where('phone', PhoneNumber::normalize($data['phone']))
            ->whereNotNull('phone_verified_at')
            ->where('type', User::TYPE_STABLE_OWNER)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['phone' => __('api.owner.no_account')]);
        }

        return $this->signedIn($user, $data['device_name'] ?? null);
    }

    /**
     * Sign in with email and password
     *
     * The /stable panel's email and password. 422 on `email` when they do not match a stable owner;
     * 429 after five wrong tries for one email (for 5 minutes).
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $key = 'owner-login|'.mb_strtolower($data['email']);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['message' => __('api.owner.throttled', ['seconds' => $seconds]), 'retry_after' => $seconds], 429);
        }

        $user = User::query()->where('email', $data['email'])->where('type', User::TYPE_STABLE_OWNER)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['email' => __('api.owner.wrong_login')]);
        }

        RateLimiter::clear($key);

        return $this->signedIn($user, $data['device_name'] ?? null);
    }

    /** Sign out (this device's token only). */
    public function logout(Request $request): JsonResponse
    {
        $request->user('owner_api')?->currentAccessToken()?->delete();

        return response()->json(['message' => __('api.account.signed_out')]);
    }

    /**
     * The signed-in owner and their stables
     *
     * Every stable they belong to (owner or staff), whatever its approval status.
     */
    public function me(Request $request): OwnerResource
    {
        return OwnerResource::make($request->user('owner_api'));
    }

    private function signedIn(User $user, ?string $device): JsonResponse
    {
        $expires = now()->addDays(self::TOKEN_DAYS);
        $token = $user->createToken($device ?: 'app', [self::ABILITY], $expires);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(),
            'owner' => OwnerResource::make($user),
        ]);
    }
}
