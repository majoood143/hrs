<?php

namespace App\Mail;

use App\Filament\Resources\StableResource;
use App\Models\StablePaymentAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A stable's own gateway keys: to the admins (new keys to review), or to the owner (the decision). */
class StablePaymentAccountMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public StablePaymentAccount $account, public string $audience, string $locale)
    {
        $this->locale($locale);
    }

    private function key(): string
    {
        return 'stable_panel.payments.mail.'.($this->audience === 'admin' ? 'submitted' : $this->account->status);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->key().'.subject', ['stable' => $this->account->stable?->name, 'gateway' => $this->account->gateway->label()]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.stables.payment-account', with: [
            'account' => $this->account,
            'key' => $this->key(),
            'url' => $this->audience === 'admin'
                ? StableResource::getUrl('view', ['record' => $this->account->stable], panel: 'admin')
                : url('/stable'),
        ]);
    }
}
