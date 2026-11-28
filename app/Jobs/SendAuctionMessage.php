<?php

namespace App\Jobs;

use App\Models\AuctionApplication;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\AuctionWatch;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\MobileDevice;
use App\Models\SiteSetting;
use App\Services\Push\ExpoPush;
use App\Services\Sms\SmsManager;
use App\Support\Locale;
use App\Support\NotificationText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One auction message to one customer: an SMS, and a push to the phones they are signed in on in the
 * app (tapping it opens the lot, the auction or My auctions). Written in their language when it is
 * sent (texts: admin → Notification Texts, `notifications.sms.auction_*`). Never throws: a message
 * must not break a bid.
 */
class SendAuctionMessage implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const APPROVED = 'approved';

    public const OUTBID = 'outbid';

    public const ENDING = 'ending';

    public const WON = 'won';

    public const INVOICE_DUE = 'invoice_due';

    // to sellers
    public const APPLICATION_APPROVED = 'application_approved';

    public const APPLICATION_REJECTED = 'application_rejected';

    public const SELLER_SOLD = 'seller_sold';

    public const SELLER_UNSOLD = 'seller_unsold';

    public const SELLER_PAID = 'seller_paid';

    private const TO_SELLER = [self::SELLER_SOLD, self::SELLER_UNSOLD, self::SELLER_PAID];

    public int $tries = 1;

    /** @param  int  $id  the bidder (approved, outbid, won, invoice_due), the watch (ending), the application (application_*) or the lot (seller_*) */
    public function __construct(public readonly string $type, public readonly int $id, public readonly ?int $lotId = null) {}

    public function handle(SmsManager $sms, ?ExpoPush $push = null): void
    {
        try {
            [$customer, $locale] = $this->recipient();
            if (! $customer) {
                return;
            }

            if ($customer->phone && $sms->canSend()) {
                $text = Locale::within($locale, fn () => NotificationText::get('sms.auction_'.$this->type, $this->replacements(), $locale));
                $sms->send($customer->phone, $text, 'auction_'.$this->type);
            }

            $devices = MobileDevice::query()->customerSignedIn($customer->id)->get();
            if ($devices->isNotEmpty()) {
                ($push ?? app(ExpoPush::class))->send($devices, fn () => [
                    'title' => __('auctions.push.'.$this->type),
                    'body' => NotificationText::get('sms.auction_'.$this->type, $this->replacements()),
                ], ['path' => $this->appPath()]);
            }
        } catch (Throwable $e) {
            Log::error('Could not send an auction message', ['type' => $this->type, 'id' => $this->id, 'error' => $e->getMessage()]);
        }
    }

    /** The app screen a tap on the push opens (the app's routes: auctions/[slug], auctions/[slug]/[number], account/auctions). */
    private function appPath(): string
    {
        if ($this->type === self::APPROVED) {
            return '/auctions/'.AuctionBidder::query()->findOrFail($this->id)->auction->slug;
        }
        if (in_array($this->type, [self::APPLICATION_APPROVED, self::APPLICATION_REJECTED, ...self::TO_SELLER], true)) {
            return '/account/auctions';
        }

        $lot = $this->type === self::ENDING ? AuctionWatch::query()->findOrFail($this->id)->lot : AuctionLot::query()->findOrFail($this->lotId);

        return '/auctions/'.$lot->auction->slug.'/'.$lot->lot_number;
    }

    /** @return array{0: ?Customer, 1: string} */
    private function recipient(): array
    {
        if (in_array($this->type, [self::APPLICATION_APPROVED, self::APPLICATION_REJECTED], true)) {
            $application = AuctionApplication::query()->find($this->id);

            return [$application?->customer, $application?->locale ?? 'en'];
        }

        if ($this->type === self::ENDING || in_array($this->type, self::TO_SELLER, true)) {
            $customer = $this->type === self::ENDING
                ? AuctionWatch::query()->find($this->id)?->customer
                : AuctionLot::query()->find($this->id)?->seller;

            return [$customer, $customer?->locale ?? 'en'];
        }

        $bidder = AuctionBidder::query()->find($this->id);

        return [$bidder?->customer, $bidder?->locale ?? 'en'];
    }

    /** @return array<string, string> */
    private function replacements(): array
    {
        $site = ['site' => SiteSetting::siteName()];

        if ($this->type === self::APPROVED) {
            $auction = AuctionBidder::query()->findOrFail($this->id)->auction;

            return $site + ['auction' => $auction->title, 'url' => route('auctions.show', $auction->slug)];
        }

        if (in_array($this->type, [self::APPLICATION_APPROVED, self::APPLICATION_REJECTED], true)) {
            $application = AuctionApplication::query()->with('lot.auction')->findOrFail($this->id);

            return $site + [
                'horse' => $application->horse_name,
                'auction' => (string) $application->lot?->auction?->title,
                'reason' => (string) $application->reject_reason,
                'url' => route('account.auctions'),
            ];
        }

        $lot = match (true) {
            $this->type === self::ENDING => AuctionWatch::query()->findOrFail($this->id)->lot,
            in_array($this->type, self::TO_SELLER, true) => AuctionLot::query()->findOrFail($this->id),
            default => AuctionLot::query()->findOrFail($this->lotId),
        };

        $invoice = in_array($this->type, [self::WON, self::INVOICE_DUE], true) ? $lot->invoice : null;

        return $site + [
            'total' => $invoice ? SiteSetting::formatCurrency($invoice->total) : '',
            'due' => $lot->payment_due_at?->timezone(BookingSlot::timezone())->translatedFormat('j M, H:i') ?? '',
            'lot' => (string) $lot->lot_number,
            'horse' => $lot->name,
            'price' => SiteSetting::formatCurrency($lot->hammer_price ?? $lot->current_price ?? $lot->starting_price),
            'minutes' => (string) max(1, (int) ceil(now()->diffInSeconds($lot->ends_at, false) / 60)),
            // the winner goes straight to the checkout of their invoice
            'payout' => SiteSetting::formatCurrency($lot->payout_amount),
            'url' => match (true) {
                (bool) $invoice?->isPayable() => route('payment.start', $invoice->order_number),
                in_array($this->type, self::TO_SELLER, true) => route('account.auctions'),
                default => $lot->url(),
            },
        ];
    }
}
