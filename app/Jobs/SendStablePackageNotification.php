<?php

namespace App\Jobs;

use App\Mail\StablePackageMail;
use App\Models\NotificationLog;
use App\Models\SiteSetting;
use App\Models\StablePackagePurchase;
use App\Services\Sms\SmsManager;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\Locale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A lesson package is ready: to its customer (in the order's language), or to its stable (by the
 * channels it chose; WhatsApp in English then Arabic). Never throws, logs every attempt.
 */
class SendStablePackageNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $purchaseId,
        public readonly string $audience,
        public readonly string $channel,
    ) {}

    public function handle(SmsManager $sms): void
    {
        $purchase = StablePackagePurchase::query()->with(['order', 'stable', 'package', 'offering'])->find($this->purchaseId);

        if (! $purchase) {
            return;
        }

        $customer = $this->audience === 'customer';
        $locale = $customer ? ($purchase->order?->locale ?: 'en') : ($purchase->stable?->ownerLocale() ?? 'en');
        $type = $customer ? 'package_ready' : 'package_sold';

        try {
            match ($this->channel) {
                'sms' => ($to = $customer ? $purchase->order?->customer_phone : $purchase->stable?->alertPhone())
                    ? $sms->send($to, Locale::within($locale, fn () => $this->text($purchase)), $type, $purchase->order)
                    : $this->log($purchase, $type, '', 'skipped', 'No phone number.'),
                'whatsapp' => ($to = $purchase->stable?->alertPhone())
                    ? app(WhatsAppNotifier::class)->send($to, BilingualMessage::make(fn () => $this->text($purchase)), $type)
                    : $this->log($purchase, $type, '', 'skipped', 'No phone number.'),
                default => $this->email($purchase, $locale, $type),
            };
        } catch (Throwable $e) {
            report($e);
            $this->log($purchase, $type, '', 'failed', $e->getMessage());
        }
    }

    /** @return array<string, string> */
    public static function data(StablePackagePurchase $purchase): array
    {
        return [
            'site' => SiteSetting::siteName(),
            'reference' => $purchase->reference,
            'package' => (string) ($purchase->package?->name ?? $purchase->name),
            'stable' => (string) $purchase->stable?->name,
            'sessions' => (string) $purchase->sessions,
            'date' => (string) $purchase->expires_at?->toDateString(),
            'customer' => (string) ($purchase->order?->customer_name ?: __('orders.guest')),
            'phone' => (string) $purchase->order?->customer_phone,
            'url' => $purchase->offering && $purchase->stable
                ? route('bookings.offering', ['stable' => $purchase->stable->slug, 'offering' => $purchase->offering->getKey()])
                : url('/'),
        ];
    }

    private function text(StablePackagePurchase $purchase): string
    {
        return __('stable_packages.sms.'.($this->audience === 'customer' ? 'ready' : 'sold'), static::data($purchase));
    }

    private function email(StablePackagePurchase $purchase, string $locale, string $type): void
    {
        $addresses = $this->audience === 'customer'
            ? array_filter([$purchase->order?->customer_email])
            : ($purchase->stable?->alertEmails() ?? []);

        foreach ($addresses as $address) {
            $mail = new StablePackageMail($purchase, $this->audience, $locale);
            Mail::to($address)->send($mail);
            $this->log($purchase, $type, $address, 'sent', Locale::within($locale, fn () => $mail->envelope()->subject));
        }
    }

    private function log(StablePackagePurchase $purchase, string $type, string $recipient, string $status, ?string $message = null): void
    {
        NotificationLog::create([
            'service_order_id' => $purchase->service_order_id,
            'customer_id' => $purchase->customer_id,
            'channel' => $this->channel,
            'type' => $type,
            'recipient' => $recipient,
            'status' => $status,
            $status === 'sent' ? 'message' : 'error' => $message,
        ]);
    }
}
