<?php

namespace App\Listeners;

use App\Events\StableRegistered;
use App\Jobs\SendWhatsAppMessage;
use App\Services\WhatsApp\StableRegistrationAlert;
use App\Support\WhatsAppSettings;
use Throwable;

/** One WhatsApp alert per admin number for a newly registered stable. Auto-discovered: never Event::listen it. */
class SendStableRegistrationWhatsAppAlert
{
    public function handle(StableRegistered $event): void
    {
        if (! WhatsAppSettings::enabled() || ! WhatsAppSettings::alertEnabled(StableRegistrationAlert::TYPE)) {
            return;
        }

        $recipients = WhatsAppSettings::recipients();

        if ($recipients === []) {
            return;
        }

        try {
            $message = StableRegistrationAlert::message($event->stable, $event->owner);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        foreach ($recipients as $phone) {
            SendWhatsAppMessage::dispatch($phone, $message, 'new_'.StableRegistrationAlert::TYPE)->afterCommit();
        }
    }
}
