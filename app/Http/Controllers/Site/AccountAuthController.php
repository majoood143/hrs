<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Auth\CustomerOtpService;
use App\Services\Auth\OtpRequestResult;
use App\Services\Sms\SmsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Customer sign-in: a phone number, then the code sent to it. The answer to "send me a code" is the
 * same for every valid number (whether or not it ever ordered anything), so the page cannot be used
 * to find out who our customers are.
 */
class AccountAuthController extends Controller
{
    private const SESSION_PHONE = 'account.otp_phone';

    private const SESSION_DEMO_CODE = 'account.demo_code';

    public function __construct(
        private readonly CustomerOtpService $otp,
        private readonly SmsManager $sms,
    ) {}

    public function login(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('account.orders');
        }

        return view('site.account.login', [
            'available' => $this->sms->canSend(),
            'seoTitle' => __('account.login_title'),
            'noindex' => true,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:32']]);

        return $this->issue($request, (string) $request->input('phone'), 'account.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_PHONE);

        if (! $phone) {
            return redirect()->route('account.login');
        }

        return view('site.account.verify', [
            'masked' => Customer::mask($phone),
            'demoCode' => $request->session()->get(self::SESSION_DEMO_CODE),
            'ttl' => CustomerOtpService::TTL_MINUTES,
            'seoTitle' => __('account.verify_title'),
            'noindex' => true,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_PHONE);

        return $phone ? $this->issue($request, $phone, 'account.verify') : redirect()->route('account.login');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $phone = $request->session()->get(self::SESSION_PHONE);

        if (! $phone) {
            return redirect()->route('account.login');
        }

        [$outcome, $customer] = $this->otp->verify($phone, (string) $request->input('code'));

        if ($outcome !== CustomerOtpService::VERIFIED) {
            return back()->withErrors(['code' => $outcome === CustomerOtpService::EXPIRED ? __('account.code_expired') : __('account.code_wrong')]);
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $request->session()->forget([self::SESSION_PHONE, self::SESSION_DEMO_CODE]);

        return redirect()->intended(route('account.orders'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('account.login')->with('status', __('account.logged_out'));
    }

    private function issue(Request $request, string $phone, string $next): RedirectResponse
    {
        $result = $this->otp->request($phone, $request->ip());

        return match ($result->status) {
            OtpRequestResult::INVALID_PHONE => back()->withInput()->withErrors(['phone' => __('account.phone_invalid')]),
            OtpRequestResult::UNAVAILABLE => back()->withInput()->withErrors(['phone' => __('account.sms_unavailable')]),
            OtpRequestResult::THROTTLED => $this->remember($request, $phone, null, $next)
                ->with('status', __('account.wait_before_resend', ['seconds' => $result->retryAfter])),
            default => $this->remember($request, $phone, $result->demoCode, $next)
                ->with('status', __('account.code_sent')),
        };
    }

    /** Keep the (validated) phone in the session so the code page knows whose code to check. */
    private function remember(Request $request, string $phone, ?string $demoCode, string $route): RedirectResponse
    {
        $request->session()->put(self::SESSION_PHONE, $this->otp->normalize($phone));
        $demoCode ? $request->session()->put(self::SESSION_DEMO_CODE, $demoCode) : $request->session()->forget(self::SESSION_DEMO_CODE);

        return redirect()->route($route);
    }
}
