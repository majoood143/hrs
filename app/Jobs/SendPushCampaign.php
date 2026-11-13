<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\PushDelivery;
use App\Services\Push\ExpoPush;
use App\Support\MobileApp\MobileAutoPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sends one push campaign to its audience, once: it claims the campaign (scheduled → sending) so a
 * second job does nothing, records a delivery per phone Expo took it for, and the counts. In quiet
 * hours it moves the time to the morning instead. Spread over N minutes, it sends a batch now and
 * keeps the other phones in `pending_devices`; `push:send-due` sends the next batch each minute
 * (claimed through `next_batch_at`, so two runs never send the same batch).
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
        $campaign = PushCampaign::query()->find($this->campaignId);

        if ($campaign?->status === 'sending') {
            $this->nextBatch($campaign, $push);

            return;
        }

        if ($campaign?->status === 'scheduled' && ($resume = $campaign->quietUntil())) {
            PushCampaign::query()->whereKey($this->campaignId)->where('status', 'scheduled')->update(['scheduled_at' => $resume]);

            return;
        }

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
        $size = $this->batchSize($campaign, $devices->count());
        $later = $devices->slice($size)->modelKeys();

        $campaign->forceFill([
            'sent_at' => now(),
            'recipients' => $devices->count(),
            'pending_devices' => $later ?: null,
            'next_batch_at' => $later ? now()->addMinute() : null,
        ])->saveQuietly();

        $this->deliver($campaign, $devices->take($size), $push);
    }

    private function nextBatch(PushCampaign $campaign, ExpoPush $push): void
    {
        $claimed = PushCampaign::query()->whereKey($campaign->getKey())->where('status', 'sending')
            ->where('next_batch_at', '<=', now())->update(['next_batch_at' => now()->addMinute()]);

        if (! $claimed) {
            return;
        }

        $campaign->refresh();
        $pending = $campaign->pending_devices ?? [];
        $size = $this->batchSize($campaign, $campaign->recipients);
        $later = array_slice($pending, $size);

        $campaign->forceFill(['pending_devices' => $later ?: null, 'next_batch_at' => $later ? now()->addMinute() : null])->saveQuietly();

        // a phone that turned notifications off (or was removed) since the start is skipped
        $this->deliver($campaign, MobileDevice::query()->whereIn('id', array_slice($pending, 0, $size))->where('enabled', true)->get(), $push);
    }

    /** Phones per batch: all at once, or an equal share for each of the spread's minutes. */
    private function batchSize(PushCampaign $campaign, int $total): int
    {
        return max(1, (int) ceil($total / max(1, (int) $campaign->spread_minutes)));
    }

    private function deliver(PushCampaign $campaign, Collection $devices, ExpoPush $push): void
    {
        $accepted = [];

        try {
            $accepted = $devices->isEmpty() ? [] : $push->send($devices, fn () => array_filter([
                'title' => $campaign->localized('title'),
                'body' => $campaign->localized('body'),
            ]), $campaign->payload(), 'default', $campaign->pushOptions());
        } catch (Throwable $e) {
            // whatever Expo took before the failure is not known; the campaign still ends as sent
            report($e);
        } finally {
            PushDelivery::query()->insert(array_map(fn (int $id) => [
                'push_campaign_id' => $campaign->getKey(),
                'mobile_device_id' => $id,
                'created_at' => now(),
            ], $accepted));

            PushCampaign::query()->whereKey($campaign->getKey())->increment('accepted', count($accepted));

            if (! $campaign->pending_devices) {
                PushCampaign::query()->whereKey($campaign->getKey())->update(['status' => 'sent', 'next_batch_at' => null]);
            }
        }
    }
}
