<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The app's WebView signing in to the website as the app's customer (`POST api/v1/account/web-session`
 * made the link): the link works once, for two minutes, then goes on to the page it was made for.
 */
class AppSignInController extends Controller
{
    public const CACHE_PREFIX = 'app-sign-in.';

    public const TTL_SECONDS = 120;

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $ticket = Cache::pull(self::CACHE_PREFIX.$token);
        $customer = is_array($ticket) ? Customer::query()->find($ticket['customer_id'] ?? null) : null;

        if (! $customer) {
            return redirect()->to(is_array($ticket) ? $ticket['path'] : '/');
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->to($ticket['path']);
    }
}
