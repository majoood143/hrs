<?php

namespace App\Mail;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Support\NotificationText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Your refund was processed": the latest refund recorded for the order, and that the service fee is not refunded. */
class OrderRefundedMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order)
    {
        $this->locale($order->locale ?: 'en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: NotificationText::get('mail.refunded.subject', ['number' => $this->order->order_number, 'site' => SiteSetting::siteName()]));
    }

    public function content(): Content
    {
        $refund = $this->order->refunds()->reorder()->latest('id')->first();

        return new Content(markdown: 'emails.orders.refunded', with: [
            'order' => $this->order,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rtl' => app()->getLocale() === 'ar',
            'amount' => $this->order->currency.' '.number_format((float) ($refund?->amount ?? 0), 3),
            'keptFee' => (float) $this->order->fee_amount > 0 ? $this->order->currency.' '.number_format($this->order->feeShare(), 3) : null,
            'url' => route('orders.show', $this->order->order_number),
        ]);
    }
}
