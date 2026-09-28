<?php

namespace App\Listeners;

use App\Events\PublicPostSubmitted;
use App\Jobs\SendWhatsAppMessage;
use App\Services\WhatsApp\NewPostAlert;
use App\Support\WhatsAppSettings;
use Throwable;

/**
 * Queues one WhatsApp alert per admin number for a new public post, when the alerts are on for
 * that kind of post. Auto-discovered (app/Listeners): never register it with Event::listen.
 */
class SendNewPostWhatsAppAlert
{
    public function handle(PublicPostSubmitted $event): void
    {
        $type = NewPostAlert::typeOf($event->post);

        if ($type === null || ! WhatsAppSettings::enabled() || ! WhatsAppSettings::alertEnabled($type)) {
            return;
        }

        $recipients = WhatsAppSettings::recipients();

        if ($recipients === []) {
            return;
        }

        try {
            $message = NewPostAlert::message($event->post);
        } catch (Throwable $e) {
            // the visitor's post is saved either way: an alert we can't build must not fail it
            report($e);

            return;
        }

        foreach ($recipients as $phone) {
            SendWhatsAppMessage::dispatch($phone, $message, 'new_'.$type)->afterCommit();
        }
    }
}
