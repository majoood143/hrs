<?php

namespace App\Mail;

use App\Models\SiteSetting;
use App\Models\Stable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a stable's owner: the scheduled sales report for a period. */
class StableSalesReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param array<string, int> $totals StableStatement::totals(), in baisa */
    public function __construct(public Stable $stable, public string $period, public array $totals, public int $balance, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('stable_statement.mail.sales_report.subject', ['stable' => $this->stable->name, 'period' => $this->period]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.stables.sales-report', with: [
            'money' => fn (int $baisa) => SiteSetting::formatCurrency($baisa / 1000),
            'balanceKey' => $this->balance > 0 ? 'we_owe' : ($this->balance < 0 ? 'you_owe' : 'settled'),
            'url' => url('/stable'),
        ]);
    }
}
