<?php

namespace App\Jobs;

use App\Mail\StableBookingMail;
use App\Models\NotificationLog;
use App\Models\StableBooking;
use App\Services\Sms\SmsManager;
use App\Services\Stables\StableBookingMessages;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One message about one booking, to its customer (in the order's language) or its stable (in its
 * owner's language; WhatsApp in English then Arabic, like every WhatsApp here). Never throws,
 * logs every attempt, and sends each message once.
 */
class SendStableBookingNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const REVIEW_INVITE = 'review_invite';

    public const REMINDER = 'reminder';

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $audience,
        public readonly string $channel,
        public readonly string $type,
    ) {}

    public function logType(): string
    {
        return 'booking_'.$this->type.($this->audience === 'stable' ? '_stable' : '');
    }

    public function handle(SmsManager $sms, StableBookingMessages $messages): void
    {
        $booking = StableBooking::query()->with(['order', 'stable', 'slot', 'offering'])->find($this->bookingId);

        if (! $booking || $this->alreadySent($booking)) {
            return;
        }

        $stable = $booking->stable;
        $order = $booking->order;
        $locale = $this->audience === 'customer' ? ($order?->locale ?: 'en') : ($stable?->ownerLocale() ?? 'en');

        try {
            match ($this->channel) {
                'sms' => $this->sms($sms, $booking, $locale, $messages),
                // built only when needed: its client needs the Evolution settings in .env
                'whatsapp' => $this->whatsapp(app(WhatsAppNotifier::class), $booking, $messages),
                default => $this->email($booking, $locale),
            };
        } catch (Throwable $e) {
            report($e);
            $this->log($booking, '', 'failed', error: $e->getMessage());
        }
    }

    private function sms(SmsManager $sms, StableBooking $booking, string $locale, StableBookingMessages $messages): void
    {
        $to = $this->audience === 'customer' ? $booking->order?->customer_phone : $booking->stable?->alertPhone();

        if (! $to) {
            $this->log($booking, '', 'skipped', error: 'No phone number.');

            return;
        }

        $text = Locale::within($locale, fn () => $this->audience === 'customer'
            ? $messages->customerSms($this->type, $booking)
            : $messages->stableText($this->type, $booking));

        // SmsManager writes the log row itself
        $sms->send($to, $text, $this->logType(), $booking->order);
    }

    private function whatsapp(WhatsAppNotifier $whatsapp, StableBooking $booking, StableBookingMessages $messages): void
    {
        $to = $booking->stable?->alertPhone();

        if (! $to) {
            $this->log($booking, '', 'skipped', error: 'No phone number.');

            return;
        }

        $whatsapp->send($to, BilingualMessage::make(fn () => $messages->stableText($this->type, $booking)), $this->logType());
    }

    private function email(StableBooking $booking, string $locale): void
    {
        $addresses = $this->audience === 'customer'
            ? array_filter([$booking->order?->customer_email])
            : ($booking->stable?->alertEmails() ?? []);

        if ($addresses === []) {
            $this->log($booking, '', 'skipped', error: 'No email address.');

            return;
        }

        foreach ($addresses as $address) {
            $mail = new StableBookingMail($booking, $this->audience, $this->type, $locale);

            try {
                Mail::to($address)->send($mail);
                $this->log($booking, $address, 'sent', Locale::within($locale, fn () => $mail->envelope()->subject));
            } catch (Throwable $e) {
                report($e);
                $this->log($booking, $address, 'failed', error: $e->getMessage());
            }
        }
    }

    private function alreadySent(StableBooking $booking): bool
    {
        return NotificationLog::query()
            ->where('service_order_id', $booking->service_order_id)
            ->where('channel', $this->channel)
            ->where('type', $this->logType())
            ->where('status', 'sent')
            ->exists();
    }

    private function log(StableBooking $booking, string $recipient, string $status, ?string $message = null, ?string $error = null): void
    {
        NotificationLog::create([
            'service_order_id' => $booking->service_order_id,
            'customer_id' => $booking->order?->customer_id,
            'channel' => $this->channel,
            'type' => $this->logType(),
            'recipient' => $recipient,
            'status' => $status,
            'message' => $message,
            'error' => $error,
        ]);
    }
}
