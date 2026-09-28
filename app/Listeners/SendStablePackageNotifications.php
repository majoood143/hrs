<?php

namespace App\Listeners;

use App\Events\StablePackageActivated;
use App\Jobs\SendStablePackageNotification;
use App\Support\WhatsAppSettings;

/** A package is ready: the customer (SMS, and email if given) and the stable (its channels). Auto-discovered. */
class SendStablePackageNotifications
{
    public function handle(StablePackageActivated $event): void
    {
        $purchase = $event->purchase;
        $order = $purchase->order;

        foreach (array_filter(['sms' => (bool) $order?->customer_phone, 'email' => (bool) $order?->customer_email]) as $channel => $_) {
            SendStablePackageNotification::dispatch($purchase->getKey(), 'customer', $channel)->afterCommit();
        }

        foreach ($purchase->stable?->bookingSettings()->alertChannels() ?? [] as $channel) {
            if ($channel === 'whatsapp' && ! WhatsAppSettings::enabled()) {
                continue;
            }

            SendStablePackageNotification::dispatch($purchase->getKey(), 'stable', $channel)->afterCommit();
        }
    }
}
