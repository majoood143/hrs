<?php

namespace App\Listeners;

use App\Events\PublicPostSubmitted;
use App\Events\StableBookingCancelled;
use App\Events\StableBookingConfirmed;
use App\Events\StablePackageActivated;
use App\Models\AppEvent;

/** The CRM's server-side events: what only the server knows for sure (paid, confirmed, cancelled). Auto-discovered. */
class RecordCrmEvents
{
    public function handleBookingConfirmed(StableBookingConfirmed $event): void
    {
        $this->booking('booking_confirmed', $event->booking);
    }

    public function handleBookingCancelled(StableBookingCancelled $event): void
    {
        $this->booking('booking_cancelled', $event->booking);
    }

    public function handlePackageActivated(StablePackageActivated $event): void
    {
        $purchase = $event->purchase;

        AppEvent::record('package_activated', $purchase->customer, [
            'reference' => $purchase->reference,
            'stable_id' => $purchase->stable_id,
            'package_id' => $purchase->stable_package_id,
            'amount' => (string) $purchase->order?->total,
        ]);
    }

    public function handlePostSubmitted(PublicPostSubmitted $event): void
    {
        // only posts from the app reach this with a customer or an install id (AppEvent::record)
        AppEvent::record('post_submitted', request()->user('customer_api'), [
            'section' => class_basename($event->post),
            'id' => $event->post->getKey(),
        ]);
    }

    private function booking(string $name, $booking): void
    {
        AppEvent::record($name, $booking->customer, [
            'reference' => $booking->reference,
            'stable_id' => $booking->stable_id,
            'offering_id' => $booking->stable_offering_id,
            'riders' => $booking->riders,
            'amount' => (string) $booking->order?->total,
        ]);
    }
}
