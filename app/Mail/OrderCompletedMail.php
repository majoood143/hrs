<?php

namespace App\Mail;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Support\NotificationText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Your order is completed". */
class OrderCompletedMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order)
    {
        $this->locale($order->locale ?: 'en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: NotificationText::get('mail.completed.subject', [
            'number' => $this->order->order_number,
            'site' => SiteSetting::siteName(),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.orders.completed', with: [
            'order' => $this->order,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rtl' => app()->getLocale() === 'ar',
            'url' => route('orders.show', $this->order->order_number),
        ]);
    }
}
