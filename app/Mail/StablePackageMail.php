<?php

namespace App\Mail;

use App\Jobs\SendStablePackageNotification;
use App\Models\StablePackagePurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A lesson package is ready (to the customer) or was sold (to the stable). */
class StablePackageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public StablePackagePurchase $purchase, public string $audience, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('stable_packages.mail.'.$this->audience.'.subject', SendStablePackageNotification::data($this->purchase)));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bookings.package', with: [
            'purchase' => $this->purchase,
            'audience' => $this->audience,
            'data' => SendStablePackageNotification::data($this->purchase),
            'url' => $this->audience === 'customer' ? SendStablePackageNotification::data($this->purchase)['url'] : url('/stable'),
        ]);
    }
}
