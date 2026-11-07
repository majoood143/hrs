<?php

namespace App\Jobs;

use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Services\Push\ExpoPush;
use App\Support\MobileApp\MobileAutoPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Sends one push campaign to its audience, once: it claims the campaign (scheduled → sending) so a
 * second job does nothing, records a delivery per phone Expo took it for, and the counts.
 */
class SendPushCampaign implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $campaignId) {}

    public function handle(ExpoPush $push): void
    {
        $claimed = PushCampaign::query()->whereKey($this->campaignId)->where('status', 'scheduled')->update(['status' => 'sending']);
        $campaign = PushCampaign::query()->find($this->campaignId);

        if (! $claimed || ! $campaign) {
            return;
        }

        // automatic: the post may have been removed, or the section switched off, since it was queued
        if ($campaign->source && ! MobileAutoPush::stillWanted($campaign)) {
            $campaign->deleteQuietly();

            return;
        }

        $devices = $campaign->devices();
        $accepted = [];

        try {
            $accepted = $push->send($devices, fn () => array_filter([
                'title' => $campaign->localized('title'),
                'body' => $campaign->localized('body'),
            ]), $campaign->payload());
        } catch (Throwable $e) {
            // whatever Expo took before the failure is not known; the campaign still ends as sent
            report($e);
        } finally {
            PushDelivery::query()->insert(array_map(fn (int $id) => [
                'push_campaign_id' => $campaign->getKey(),
                'mobile_device_id' => $id,
                'created_at' => now(),
            ], $accepted));

            $campaign->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'recipients' => $devices->count(),
                'accepted' => count($accepted),
            ])->saveQuietly();
        }
    }
}
