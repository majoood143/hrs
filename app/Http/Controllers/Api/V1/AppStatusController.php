<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AnnouncementResource;
use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileMaintenance;
use App\Support\MobileApp\MobileUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the app must do right now (admin → Mobile app): maintenance, update, feature switches and
 * popups, for the platform and version it names in `X-App-Platform` / `X-App-Version`.
 */
class AppStatusController extends Controller
{
    /**
     * App status
     *
     * Call on every launch and when the app returns to the foreground; keep the last answer for
     * offline launches. Order of importance: maintenance → required update → optional update →
     * one announcement. Features are `on`, `hidden` (remove it everywhere) or `soon` (show it as
     * "Coming soon"). Send `X-App-Platform` (ios | android) and `X-App-Version` (e.g. 1.2.0):
     * without them no update is asked for and platform/version rules are not applied.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request): JsonResponse
    {
        $client = AppClient::fromRequest($request);
        $locale = app()->getLocale();

        $announcements = MobileAnnouncement::query()->live()
            ->orderByDesc('priority')->orderByDesc('id')
            ->get()
            ->filter(fn (MobileAnnouncement $announcement) => $announcement->shownTo($client, $locale))
            ->values();

        return response()->json([
            'data' => [
                /** @var array{enabled: bool, message: string|null} */
                'maintenance' => MobileMaintenance::forClient(),
                /** @var array{status: 'none'|'optional'|'required', latest_version: string|null, min_version: string|null, store_url: string|null, store_app_url: string|null, message: string|null} */
                'update' => MobileUpdates::forClient($client),
                /** @var array<string, 'on'|'hidden'|'soon'> */
                'features' => MobileFeatures::forClient($client),
                'announcements' => AnnouncementResource::collection($announcements),
                /** What the server understood from the headers (null = not sent or not recognised). */
                'client' => ['platform' => $client->platform, 'version' => $client->version],
            ],
        ])->header('Cache-Control', 'no-store')->setVary(['X-App-Platform', 'X-App-Version'], false);
    }
}
