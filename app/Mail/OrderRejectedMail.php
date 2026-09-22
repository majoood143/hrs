<?php

namespace App\Mail;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Support\NotificationText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "We could not approve your request": with the reviewer's reason, and what happens to a payment. */
class OrderRejectedMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order)
    {
        $this->locale($order->locale ?: 'en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: NotificationText::get('mail.rejected.subject', ['number' => $this->order->order_number, 'site' => SiteSetting::siteName()]));
    }

    public function content(): Content
    {
        $refundable = $this->order->isPaid() ? $this->order->refundableAmount() : 0.0;

        return new Content(markdown: 'emails.orders.rejected', with: [
            'order' => $this->order,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rtl' => app()->getLocale() === 'ar',
            'reason' => $this->order->rejectionReason(),
            'refund' => $refundable > 0 ? $this->order->currency.' '.number_format($refundable, 3) : null,
            'url' => route('orders.show', $this->order->order_number),
        ]);
    }
}
