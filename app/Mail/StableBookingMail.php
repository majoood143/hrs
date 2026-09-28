<?php

namespace App\Mail;

use App\Models\StableBooking;
use App\Services\Stables\StableBookingMessages;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A booking confirmed or cancelled, to its customer or to its stable. */
class StableBookingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public StableBooking $booking,
        public string $audience,
        public string $type,
        string $locale,
    ) {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("stable_bookings.mail.{$this->audience}_{$this->type}.subject", app(StableBookingMessages::class)->data($this->booking)));
    }

    public function content(): Content
    {
        $messages = app(StableBookingMessages::class);

        return new Content(markdown: 'emails.bookings.booking', with: [
            'booking' => $this->booking,
            'audience' => $this->audience,
            'type' => $this->type,
            'data' => $messages->data($this->booking),
            'refundDue' => $messages->refundDue($this->booking),
            'rtl' => in_array(app()->getLocale(), ['ar'], true),
        ]);
    }
}
