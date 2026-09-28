<?php

namespace App\Mail;

use App\Filament\Resources\StableResource;
use App\Models\Stable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the admins who approve stables: a new one is waiting. */
class StableRegisteredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Stable $stable, public User $owner)
    {
        $this->locale(config('languages.default', 'en'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('stable_panel.mail.registered.subject', ['stable' => $this->stable->en_name]));
    }

    public function content(): Content
    {
        $this->stable->loadMissing(['region', 'city']);

        return new Content(markdown: 'emails.stables.registered', with: [
            'stable' => $this->stable,
            'owner' => $this->owner,
            'adminUrl' => StableResource::getUrl('view', ['record' => $this->stable], panel: 'admin'),
        ]);
    }
}
