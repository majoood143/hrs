<?php

namespace App\Mail;

use App\Models\StableSettlement;
use App\Services\Reports\StableStatement;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a stable's owner: we paid them, or we received their payment, and where the balance now stands. */
class StableSettlementMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public StableSettlement $settlement, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('stable_statement.mail.'.$this->settlement->direction.'.subject', [
            'amount' => Money::format($this->settlement->amountBaisa()),
            'stable' => $this->settlement->stable?->name,
        ]));
    }

    public function content(): Content
    {
        $balance = $this->settlement->stable ? StableStatement::balance($this->settlement->stable) : 0;

        return new Content(markdown: 'emails.stables.settlement', with: [
            'settlement' => $this->settlement,
            'amount' => Money::format($this->settlement->amountBaisa()),
            'balance' => Money::format(abs($balance)),
            'balanceKey' => $balance > 0 ? 'we_owe' : ($balance < 0 ? 'you_owe' : 'settled'),
            'url' => url('/stable'),
        ]);
    }
}
