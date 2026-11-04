<?php

namespace App\Mail;

use App\Models\StableBooking;
use App\Services\Stables\BookingTicketPdf;
use App\Services\Stables\StableBookingMessages;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** A booking confirmed or cancelled, to its customer (a confirmation with its ticket) or to its stable. */
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

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $tickets = app(BookingTicketPdf::class);

        if ($this->audience !== 'customer' || $this->type !== 'confirmed' || ! $tickets->available($this->booking)) {
            return [];
        }

        // a ticket that cannot be drawn must never stop the customer hearing about their booking
        try {
            $pdf = $tickets->render($this->booking);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return [Attachment::fromData(fn () => $pdf, $tickets->filename($this->booking))->withMime('application/pdf')];
    }
}
