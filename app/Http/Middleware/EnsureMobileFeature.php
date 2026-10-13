<?php

namespace App\Http\Middleware;

use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileMaintenance;
use App\Support\MobileApp\MobileUpdates;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the app's write endpoints (posting): refused while the app is in maintenance, for an app
 * below the minimum version, and while the feature is off for this app, whatever an old or altered
 * app shows. `EnsureMobileFeature::class.':post_horse'`; several features = any of them on.
 */
class EnsureMobileFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $client = AppClient::fromRequest($request);

        if (MobileMaintenance::enabled()) {
            return $this->refuse('maintenance', MobileMaintenance::forClient()['message'], 503);
        }

        if (MobileUpdates::forClient($client)['status'] === MobileUpdates::REQUIRED) {
            return $this->refuse('update_required', MobileUpdates::forClient($client)['message'], 426);
        }

        foreach ($features as $feature) {
            if (MobileFeatures::enabledFor($feature, $client)) {
                return $next($request);
            }
        }

        return $this->refuse('feature_disabled', __('api.app.feature_disabled'), 403);
    }

    private function refuse(string $code, ?string $message, int $status): Response
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
