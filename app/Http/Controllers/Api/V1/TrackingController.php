<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppEvent;
use App\Support\MobileApp\AppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** What people do in the app, for the CRM (admin → CRM): sent in batches, like CleverTap's events. */
class TrackingController extends Controller
{
    public const MAX_BATCH = 50;

    /** How far back the phone's clock is believed (events kept offline); older or future times become now. */
    public const MAX_AGE_DAYS = 7;

    /**
     * Record app events
     *
     * Needs `X-Install-Id` (the app's anonymous id for this install, kept until it is uninstalled).
     * With a customer's bearer token the events are theirs; before sign-in they are the install's
     * and become the customer's when `auth/verify` is called with the same header. Up to 50 per call;
     * names the server does not know are dropped (`accepted` counts the rest). Properties: up to 20
     * flat values (no lists or objects).
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $install = AppEvent::installId($request);

        if ($install === null) {
            throw ValidationException::withMessages(['install_id' => __('api.events.install_required')]);
        }

        $data = $request->validate([
            'events' => ['required', 'array', 'max:'.self::MAX_BATCH],
            /** @example listing_viewed */
            'events.*.name' => ['required', 'string', 'max:40'],
            /** @var array<string, string|int|float|bool|null> */
            'events.*.properties' => ['nullable', 'array'],
            /** The route the app was on. @example /horses/12 */
            'events.*.screen' => ['nullable', 'string', 'max:120'],
            /** When it happened on the phone (ISO 8601). */
            'events.*.at' => ['nullable', 'date'],
        ]);

        $client = AppClient::fromRequest($request);
        $customer = $request->user('customer_api');
        $now = now();
        $oldest = $now->copy()->subDays(self::MAX_AGE_DAYS);

        $rows = collect($data['events'])
            ->filter(fn (array $event) => in_array($event['name'], AppEvent::CLIENT, true))
            ->map(function (array $event) use ($install, $customer, $client, $now, $oldest) {
                $at = isset($event['at']) ? Carbon::parse($event['at'])->utc() : $now;
                $properties = AppEvent::cleanProperties($event['properties'] ?? []);

                return [
                    'install_id' => $install,
                    'customer_id' => $customer?->getKey(),
                    'name' => $event['name'],
                    'properties' => $properties ? json_encode($properties, JSON_UNESCAPED_UNICODE) : null,
                    'screen' => $event['screen'] ?? null,
                    'platform' => $client->platform,
                    'app_version' => $client->version,
                    'locale' => app()->getLocale(),
                    'occurred_at' => $at->between($oldest, $now) ? $at : $now,
                    'created_at' => $now,
                ];
            })
            ->values();

        if ($rows->isNotEmpty()) {
            AppEvent::query()->insert($rows->all());
        }

        return response()->json(['accepted' => $rows->count()], 202);
    }
}
