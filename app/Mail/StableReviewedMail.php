<?php

namespace App\Mail;

use App\Models\Stable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a stable's owner: the admins approved, rejected or suspended it. */
class StableReviewedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Stable $stable, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('stable_panel.mail.reviewed.'.$this->stable->approval_status->value.'.subject', ['stable' => $this->stable->name]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.stables.reviewed', with: [
            'stable' => $this->stable,
            'status' => $this->stable->approval_status->value,
            'panelUrl' => url('/stable'),
        ]);
    }
}
