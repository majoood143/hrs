<?php

namespace App\Mail;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Orders\OrderReceiptPdf;
use App\Support\NotificationText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Throwable;

/** "We have your order": its number, what was paid, and (once paid) the receipt attached. */
class OrderReceivedMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order)
    {
        $this->locale($order->locale ?: 'en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: NotificationText::get($this->order->isPaid() ? 'mail.received.subject_paid' : 'mail.received.subject_free', [
            'number' => $this->order->order_number,
            'site' => SiteSetting::siteName(),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.orders.received', with: [
            'order' => $this->order,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rtl' => app()->getLocale() === 'ar',
            'url' => route('orders.show', $this->order->order_number),
            'money' => fn ($amount) => $this->order->currency.' '.number_format((float) $amount, 3),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $receipts = app(OrderReceiptPdf::class);

        if (! $receipts->available($this->order)) {
            return [];
        }

        // a receipt that cannot be drawn must never stop the customer hearing about their order
        try {
            $pdf = $receipts->render($this->order, $this->order->locale);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return [Attachment::fromData(fn () => $pdf, $receipts->filename($this->order))->withMime('application/pdf')];
    }
}
