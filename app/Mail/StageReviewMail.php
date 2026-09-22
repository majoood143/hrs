<?php

namespace App\Mail;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the holders of a stage's role: this order is waiting for your decision. */
class StageReviewMail extends Mailable
{
    use Queueable;

    public function __construct(public ServiceOrder $order, public OrderStage $stage)
    {
        $this->locale(config('languages.default', 'en'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.mail.stage.subject', [
            'number' => $this->order->order_number,
            'stage' => $this->stage->name,
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.orders.stage-review', with: [
            'order' => $this->order,
            'stage' => $this->stage->name,
            'serviceName' => $this->order->service?->localizedName() ?? __('orders.service'),
            'rows' => $this->order->submission?->formatted() ?? [],
            'adminUrl' => ServiceOrderResource::getUrl('view', ['record' => $this->order->getKey()]),
        ]);
    }
}
