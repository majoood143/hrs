<?php

namespace App\Mail;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the people who handle a form: a paid order is in, with what the customer wrote. */
class ReviewerOrderMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order)
    {
        $this->locale(config('languages.default', 'en'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.mail.reviewer.subject', [
            'number' => $this->order->order_number,
            'service' => $this->order->service?->localizedName() ?? __('orders.service'),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.orders.reviewer', with: [
            'order' => $this->order,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rows' => $this->order->submission?->formatted() ?? [],
            'adminUrl' => ServiceOrderResource::getUrl('view', ['record' => $this->order->getKey()]),
            'total' => $this->order->currency.' '.number_format((float) $this->order->total, 3),
        ]);
    }
}
