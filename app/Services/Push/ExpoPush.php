<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use App\Support\Locale;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Push notifications through Expo's push service (it hands them to Apple and Google). Each phone
 * gets the text in its own language; a phone the app was removed from (DeviceNotRegistered, in the
 * ticket now or in the receipt `push:check-receipts` reads later) is marked uninstalled; a phone with
 * the app's main switch off gets nothing.
 */
class ExpoPush
{
    public const URL = 'https://exp.host/--/api/v2/push/send';

    public const RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';

    /**
     * @param  Collection<int, MobileDevice>  $devices
     * @param  callable(): array{title: string, body: string}  $message  called in each phone's language
     * @param  array<string, mixed>  $data  given to the app on a tap (e.g. the screen to open)
     * @param  array<string, mixed>  $options  Expo's message fields that override the defaults (priority, ttl, sound: null = silent, channelId)
     * @return list<int> the ids of the phones Expo accepted it for
     */
    public function send(Collection $devices, callable $message, array $data = [], string $channel = 'default', array $options = []): array
    {
        $accepted = [];
        $removed = [];
        $receipts = [];

        // Expo takes at most 100 messages a request
        foreach ($devices->reject(fn (MobileDevice $device) => $device->enabled === false)->chunk(100) as $chunk) {
            $chunk = $chunk->values();
            $messages = $chunk->map(fn (MobileDevice $device) => Locale::within($device->locale, $message) + [
                'to' => $device->expo_token,
                'data' => $data,
            ] + $options + [
                'sound' => 'default',
                'channelId' => $channel,
            ])->all();

            try {
                $tickets = Http::acceptJson()->timeout(15)->post(self::URL, $messages)->throw()->json('data', []);
            } catch (HttpClientException $e) {
                // one failed batch must not drop the others (nor the phones already accepted)
                report($e);

                continue;
            }

            foreach ($tickets as $i => $ticket) {
                if (! isset($chunk[$i])) {
                    continue;
                }

                if (($ticket['status'] ?? null) === 'ok') {
                    $accepted[] = $chunk[$i]->getKey();

                    if (isset($ticket['id'])) {
                        $receipts[] = ['ticket_id' => (string) $ticket['id'], 'mobile_device_id' => $chunk[$i]->getKey(), 'created_at' => now()];
                    }
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    $removed[] = $chunk[$i]->getKey();
                }
            }
        }

        MobileDevice::markUninstalled($removed);
        DB::table('push_receipts')->insert($receipts);

        // "delivered" = Expo took it; the receipts (push:check-receipts) are read only for removed phones
        return $accepted;
    }

    /**
     * Reads Expo's receipts for tickets older than 15 minutes (Expo keeps them about a day) and marks
     * the phones Apple/Google no longer know. Checked tickets are dropped; those Expo has no answer
     * for yet wait, until a day old.
     *
     * @return int phones marked uninstalled
     */
    public function checkReceipts(): int
    {
        DB::table('push_receipts')->where('created_at', '<', now()->subDay())->delete();
        $removed = 0;

        DB::table('push_receipts')->where('created_at', '<=', now()->subMinutes(15))
            ->chunkById(1000, function (Collection $rows) use (&$removed) {
                try {
                    $answers = Http::acceptJson()->timeout(15)->post(self::RECEIPTS_URL, ['ids' => $rows->pluck('ticket_id')->all()])->throw()->json('data', []);
                } catch (HttpClientException $e) {
                    report($e); // tried again at the next run

                    return;
                }

                $answered = $rows->filter(fn ($row) => isset($answers[$row->ticket_id]));
                $gone = $answered->filter(fn ($row) => ($answers[$row->ticket_id]['details']['error'] ?? null) === 'DeviceNotRegistered')
                    ->pluck('mobile_device_id')->unique()->values()->all();

                MobileDevice::markUninstalled($gone);
                DB::table('push_receipts')->whereIn('id', $answered->pluck('id'))->delete();
                $removed += count($gone);
            });

        return $removed;
    }
}
