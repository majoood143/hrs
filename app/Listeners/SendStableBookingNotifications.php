<?php

namespace App\Listeners;

use App\Events\StableBookingCancelled;
use App\Events\StableBookingConfirmed;
use App\Jobs\SendStableBookingNotification;
use App\Models\StableBooking;
use App\Support\WhatsAppSettings;

/**
 * Tells the customer (email when they gave one, SMS, and their ticket by WhatsApp) and the stable (by the channels it chose
 * in its booking settings, plus a push to the app) that a booking was confirmed or cancelled. One queued job per message.
 * Auto-discovered (app/Listeners): never Event::listen it.
 */
class SendStableBookingNotifications
{
    public function handleConfirmed(StableBookingConfirmed $event): void
    {
        $this->dispatch($event->booking, SendStableBookingNotification::CONFIRMED);
    }

    public function handleCancelled(StableBookingCancelled $event): void
    {
        $this->dispatch($event->booking, SendStableBookingNotification::CANCELLED);
    }

    private function dispatch(StableBooking $booking, string $type): void
    {
        $order = $booking->order;

        // the customer: SMS always (a booking needs a phone), email when there is one
        foreach (array_filter(['sms' => (bool) $order?->customer_phone, 'email' => (bool) $order?->customer_email]) as $channel => $_) {
            SendStableBookingNotification::dispatch($booking->getKey(), 'customer', $channel, $type)->afterCommit();
        }

        // their ticket by WhatsApp, while the site's WhatsApp and its "Booking tickets" alert are on
        if ($type === SendStableBookingNotification::CONFIRMED && $order?->customer_phone && WhatsAppSettings::enabled() && WhatsAppSettings::alertEnabled('booking_ticket')) {
            SendStableBookingNotification::dispatch($booking->getKey(), 'customer', 'whatsapp', $type)->afterCommit();
        }

        // and a push to whoever is signed in on a phone: the customer, and the stable's owners and staff
        foreach (['customer', 'stable'] as $audience) {
            if (SendStableBookingNotification::pushDevices($booking, $audience)->isNotEmpty()) {
                SendStableBookingNotification::dispatch($booking->getKey(), $audience, 'push', $type)->afterCommit();
            }
        }

        // the stable, as its owner chose (WhatsApp only while the site's WhatsApp is switched on)
        foreach ($booking->stable?->bookingSettings()->alertChannels() ?? [] as $channel) {
            if ($channel === 'whatsapp' && ! WhatsAppSettings::enabled()) {
                continue;
            }

            SendStableBookingNotification::dispatch($booking->getKey(), 'stable', $channel, $type)->afterCommit();
        }
    }
}
