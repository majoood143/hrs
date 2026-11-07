<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use App\Support\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Push notifications through Expo's push service (it hands them to Apple and Google). Each phone
 * gets the text in its own language; a phone the app was removed from (DeviceNotRegistered) is
 * forgotten; a phone with the app's main switch off gets nothing.
 */
class ExpoPush
{
    public const URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  Collection<int, MobileDevice>  $devices
     * @param  callable(): array{title: string, body: string}  $message  called in each phone's language
     * @param  array<string, mixed>  $data  given to the app on a tap (e.g. the screen to open)
     * @return list<int> the ids of the phones Expo accepted it for
     */
    public function send(Collection $devices, callable $message, array $data = [], string $channel = 'default'): array
    {
        $accepted = [];

        // Expo takes at most 100 messages a request
        foreach ($devices->reject(fn (MobileDevice $device) => $device->enabled === false)->chunk(100) as $chunk) {
            $chunk = $chunk->values();
            $messages = $chunk->map(fn (MobileDevice $device) => Locale::within($device->locale, $message) + [
                'to' => $device->expo_token,
                'data' => $data,
                'sound' => 'default',
                'channelId' => $channel,
            ])->all();

            $tickets = Http::acceptJson()->timeout(15)->post(self::URL, $messages)->throw()->json('data', []);

            foreach ($tickets as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    $accepted[] = $chunk[$i]->getKey();
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    $chunk[$i]?->delete();
                }
            }
        }

        // shortcut: only the immediate tickets are read, not Expo's later receipts ("delivered" = Expo took it); read receipts if that number must be exact
        return $accepted;
    }
}
