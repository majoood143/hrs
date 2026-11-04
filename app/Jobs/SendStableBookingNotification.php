<?php

namespace App\Jobs;

use App\Mail\StableBookingMail;
use App\Models\MobileDevice;
use App\Models\NotificationLog;
use App\Models\StableBooking;
use App\Services\Push\ExpoPush;
use App\Services\Sms\SmsManager;
use App\Services\Stables\BookingTicketPdf;
use App\Services\Stables\StableBookingMessages;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One message about one booking, to its customer (in the order's language) or its stable (in its
 * owner's language; WhatsApp in English then Arabic, like every WhatsApp here; a push in each phone's
 * language, to the customer or the owners and staff signed in on it in the app; to the customer, WhatsApp
 * carries their ticket). Never throws,
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
                'push' => $this->push(app(ExpoPush::class), $booking, $messages),
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
        if ($this->audience === 'customer') {
            $this->ticket($whatsapp, $booking, $messages);

            return;
        }

        $to = $booking->stable?->alertPhone();

        if (! $to) {
            $this->log($booking, '', 'skipped', error: 'No phone number.');

            return;
        }

        $whatsapp->send($to, BilingualMessage::make(fn () => $messages->stableText($this->type, $booking)), $this->logType());
    }

    /** The customer's ticket (PDF, in their language) with a caption in English then Arabic: confirmation and reminder. */
    private function ticket(WhatsAppNotifier $whatsapp, StableBooking $booking, StableBookingMessages $messages): void
    {
        $tickets = app(BookingTicketPdf::class);
        $to = $booking->order?->customer_phone;

        if (! $to || ! $tickets->available($booking)) {
            $this->log($booking, (string) $to, 'skipped', error: $to ? 'No ticket for this booking.' : 'No phone number.');

            return;
        }

        $caption = BilingualMessage::make(fn () => __('whatsapp.booking_ticket.'.($this->type === self::REMINDER ? 'reminder' : 'confirmed'), $messages->data($booking)));

        // the notifier writes the log row; it is tied to the order so this message goes out once
        $whatsapp->sendDocument($to, $tickets->render($booking), $tickets->filename($booking), $caption, $this->logType())
            ->update(['service_order_id' => $booking->service_order_id, 'customer_id' => $booking->order?->customer_id]);
    }

    /** The signed-in phones a push about this booking goes to: the customer's, or the stable's owners' and staff's. */
    public static function pushDevices(StableBooking $booking, string $audience): Collection
    {
        return $audience === 'customer'
            ? MobileDevice::query()->customerSignedIn($booking->order?->customer_id)->get()
            : MobileDevice::query()->ownersSignedIn($booking->stable?->members()->pluck('users.id')->all() ?? [])->get();
    }

    private function push(ExpoPush $push, StableBooking $booking, StableBookingMessages $messages): void
    {
        $key = 'stable_bookings.push.'.($this->audience === 'customer' ? 'customer_' : '').$this->type;
        $data = $this->audience === 'customer'
            ? ['path' => '/account/bookings/'.$booking->reference]
            : ['path' => '/owner/bookings/'.$booking->reference, 'stable' => $booking->stable?->slug];

        $sent = count($push->send(self::pushDevices($booking, $this->audience), fn () => [
            'title' => __($key.'_title', $messages->data($booking)),
            'body' => __($key, $messages->data($booking)),
        ], $data, 'bookings'));

        $this->log($booking, $sent.' '.str('phone')->plural($sent), $sent > 0 ? 'sent' : 'skipped', error: $sent > 0 ? null : 'No phone took it.');
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
