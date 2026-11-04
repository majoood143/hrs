<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PushMessageResource;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Models\PushSavedSearch;
use App\Support\MobileApp\AppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Push notifications for every phone, signed in or not: the phone registers its Expo push token and
 * the topics it takes; the app's notifications list and "opened" are by that token.
 */
class PushController extends Controller
{
    public const TOKEN_RULE = ['required', 'string', 'max:200', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'];

    /** How far back the app's notifications list goes. */
    public const INBOX_DAYS = 60;

    /**
     * Register this phone
     *
     * Sent at every launch while notifications are allowed, with the topics the person left on
     * (`news`, `offers`, `marketplace`, `events`, `racing`; leave `topics` out to keep them as they
     * are, new phones take all). With a customer's bearer token, the customer's booking alerts come
     * to this phone until that sign-in ends; without one, the phone is no longer the customer's.
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => self::TOKEN_RULE,
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string', 'in:'.implode(',', MobileDevice::TOPICS)],
        ]);
        $client = AppClient::fromRequest($request);
        $customer = $request->user('customer_api');

        MobileDevice::query()->updateOrCreate(['expo_token' => $data['token']], [
            'platform' => $client->platform,
            'app_version' => $client->version,
            'locale' => app()->getLocale(),
            'customer_id' => $customer?->getKey(),
            'customer_token_id' => $customer?->currentAccessToken()?->getKey(),
            'last_seen_at' => now(),
        ] + (array_key_exists('topics', $data) ? ['topics' => array_values(array_unique($data['topics']))] : []));

        return response()->json(['message' => 'ok']);
    }

    /**
     * This phone's notifications
     *
     * The campaigns sent to the phone named by the `X-Push-Token` header in the last 60 days, newest
     * first, in the request's language (booking alerts are not listed: they open the booking).
     *
     * @unauthenticated
     */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        $device = $this->device($request);

        $deliveries = PushDelivery::query()
            ->with('campaign')
            ->where('mobile_device_id', $device?->getKey())
            ->where('created_at', '>=', now()->subDays(self::INBOX_DAYS))
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(fn (PushDelivery $delivery) => $delivery->campaign !== null);

        return PushMessageResource::collection($deliveries->values());
    }

    /**
     * A notification was opened
     *
     * Counts it once per phone (the campaign's "Opened"), from a tap on the notification or in the list.
     *
     * @unauthenticated
     */
    public function opened(Request $request, int $campaign): JsonResponse
    {
        $request->validate(['token' => self::TOKEN_RULE]);
        $device = MobileDevice::query()->where('expo_token', $request->input('token'))->first();

        $first = PushDelivery::query()
            ->where('push_campaign_id', $campaign)
            ->where('mobile_device_id', $device?->getKey())
            ->whereNull('opened_at')
            ->update(['opened_at' => now()]);

        if ($first) {
            PushCampaign::query()->whereKey($campaign)->increment('opened');
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * This phone's saved searches
     *
     * By the `X-Push-Token` header, newest first, each with a label in the request's language.
     *
     * @unauthenticated
     *
     * @return array{data: list<array{id: int, section: string, label: string, filters: array<string, string>}>}
     */
    public function searches(Request $request): array
    {
        $searches = PushSavedSearch::query()->where('mobile_device_id', $this->device($request)?->getKey())->latest('id')->get();

        return ['data' => $searches->map(fn (PushSavedSearch $search) => [
            'id' => $search->id,
            'section' => $search->section,
            'label' => $search->label(),
            'filters' => $search->filters,
        ])->all()];
    }

    /**
     * Save a search
     *
     * A board's filters (named like its query string; others are dropped): a post going live that
     * matches them is pushed to this phone. Up to 10 per phone (422 on `section` past that); the
     * same filters again are kept once.
     *
     * @unauthenticated
     */
    public function saveSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => self::TOKEN_RULE,
            'section' => ['required', Rule::in(array_keys(PushSavedSearch::SECTIONS))],
            'filters' => ['present', 'array'],
            // one value per filter (a number or a word), never a list
            'filters.*' => ['nullable', 'max:255', fn (string $attribute, mixed $value, \Closure $fail) => is_scalar($value) || $value === null ? null : $fail(__('validation.string', ['attribute' => $attribute]))],
        ]);
        $device = MobileDevice::query()->where('expo_token', $data['token'])->firstOrFail();
        [, $keys] = PushSavedSearch::SECTIONS[$data['section']];
        $filters = array_map('strval', array_filter(array_intersect_key($data['filters'], array_flip($keys)), fn ($v) => filled($v)));
        ksort($filters);

        $search = PushSavedSearch::query()
            ->where('mobile_device_id', $device->getKey())
            ->where('section', $data['section'])
            ->get()
            ->first(fn (PushSavedSearch $saved) => $saved->filters == $filters);

        if (! $search) {
            if (PushSavedSearch::query()->where('mobile_device_id', $device->getKey())->count() >= PushSavedSearch::MAX_PER_PHONE) {
                throw ValidationException::withMessages(['section' => __('api.push.search_limit', ['count' => PushSavedSearch::MAX_PER_PHONE])]);
            }

            $search = PushSavedSearch::create(['mobile_device_id' => $device->getKey(), 'section' => $data['section'], 'filters' => $filters]);
        }

        return response()->json(['data' => ['id' => $search->id, 'label' => $search->label()]], 201);
    }

    /**
     * Remove a saved search
     *
     * One of this phone's (`X-Push-Token` header); 404 otherwise.
     *
     * @unauthenticated
     */
    public function deleteSearch(Request $request, int $id): JsonResponse
    {
        PushSavedSearch::query()->whereKey($id)->where('mobile_device_id', $this->device($request)?->getKey())->firstOrFail()->delete();

        return response()->json(['message' => 'ok']);
    }

    private function device(Request $request): ?MobileDevice
    {
        $token = (string) $request->header('X-Push-Token');

        return $token === '' ? null : MobileDevice::query()->where('expo_token', $token)->first();
    }
}
