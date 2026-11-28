<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\AppSignInController;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A one-time link that signs the app's WebView in to the website as this customer, then opens a page
 * of the website (a page with a game, which needs the player). Only a path of this website: the link
 * can never send anyone elsewhere.
 */
class WebSessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            // a path of this site: "/x", never "//host" or a backslash trick
            'path' => ['required', 'string', 'max:500', 'regex:#^/(?![/\\\\])[^\\\\\s]*$#'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user('customer_api');
        $token = Str::random(48);

        Cache::put(AppSignInController::CACHE_PREFIX.$token, ['customer_id' => $customer->id, 'path' => $data['path']], AppSignInController::TTL_SECONDS);

        return response()->json([
            /** The link to open in the WebView: it works once, within `expires_in` seconds. */
            'url' => route('account.app-sign-in', $token),
            'expires_in' => AppSignInController::TTL_SECONDS,
        ]);
    }
}
