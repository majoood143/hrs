<?php

namespace App\Services\Stables;

use App\Enums\StableBookingStatus;
use App\Models\SiteSetting;
use App\Models\StableBooking;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\Locale;
use App\Support\PdfText;
use Illuminate\Support\Facades\URL;
use Mpdf\Output\Destination;
use Mpdf\QrCode\Output\Png;
use Mpdf\QrCode\QrCode;

/**
 * A confirmed booking's ticket: the site's logo, brand colour and fonts, the session, and a QR code
 * of its check-in link (`/check-in/{reference}`) that the stable scans to mark it attended. In the
 * customer's language, phone-sized. Sent with the confirmation (email, WhatsApp) and the reminder,
 * and downloadable from a signed link (website account, app). The public order page, which opens with
 * the order number alone, links to an `anonymous` copy: same code, without the customer's and riders' names.
 */
class BookingTicketPdf
{
    /** How long a download link stays good. */
    public const LINK_DAYS = 7;

    public function __construct(private readonly MpdfFactory $factory) {}

    public function available(StableBooking $booking): bool
    {
        return in_array($booking->status, [StableBookingStatus::Confirmed, StableBookingStatus::Completed], true);
    }

    public function filename(StableBooking $booking): string
    {
        return 'ticket-'.$booking->reference.'.pdf';
    }

    /** What the QR code holds: a page that opens the stable's check-in (or the app's) for this booking. */
    public function checkInUrl(StableBooking $booking): string
    {
        return route('bookings.check-in', $booking->reference);
    }

    /**
     * A signed download link, for the account pages and the app; null while there is no ticket.
     * The signature covers `guest`, so an anonymous link cannot be edited into the named ticket.
     */
    public function url(StableBooking $booking, bool $anonymous = false): ?string
    {
        return $this->available($booking)
            ? URL::temporarySignedRoute('bookings.ticket', now()->addDays(self::LINK_DAYS), ['reference' => $booking->reference] + ($anonymous ? ['guest' => 1] : []))
            : null;
    }

    /** The ticket's QR code alone (a PNG data URI), for the app's booking screen and the order pages; null while there is no ticket. */
    public function qrDataUri(StableBooking $booking): ?string
    {
        if (! $this->available($booking)) {
            return null;
        }

        $png = (new Png)->output(new QrCode($this->checkInUrl($booking), 'M'), 360, [255, 255, 255], [0, 0, 0], 9);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** A signed link to the order's receipt (the account page's one needs the website sign-in), in that language; null until paid. */
    public function receiptUrl(StableBooking $booking, string $locale): ?string
    {
        return $booking->order && app(OrderReceiptPdf::class)->available($booking->order)
            ? URL::temporarySignedRoute('bookings.receipt', now()->addDays(self::LINK_DAYS), ['reference' => $booking->reference, 'lang' => $locale])
            : null;
    }

    public function locale(StableBooking $booking): string
    {
        $locale = $booking->order?->locale;

        return in_array($locale, array_keys(config('languages.available', [])), true) ? $locale : 'en';
    }

    public function html(StableBooking $booking, ?string $locale = null, bool $anonymous = false): string
    {
        $locale ??= $this->locale($booking);
        $booking->loadMissing(['slot.trainer', 'offering', 'stable.city', 'order', 'packagePurchase']);

        return Locale::within($locale, function () use ($booking, $locale, $anonymous) {
            $rtl = $locale === 'ar';
            $branding = SiteSetting::branding();

            return view('pdf.booking-ticket', [
                'booking' => $booking,
                'anonymous' => $anonymous,
                'data' => app(StableBookingMessages::class)->data($booking),
                'locale' => $locale,
                'rtl' => $rtl,
                'siteName' => SiteSetting::siteName(),
                'siteUrl' => preg_replace('#^https?://#', '', rtrim(url('/'), '/')),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'primary' => $branding['primary'],
                'headingFont' => MpdfFactory::brandFont($branding['heading_font'], $rtl),
                'bodyFont' => MpdfFactory::brandFont($branding['body_font'], $rtl),
                'qrUrl' => $this->checkInUrl($booking),
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });
    }

    public function render(StableBooking $booking, ?string $locale = null, bool $anonymous = false): string
    {
        $locale ??= $this->locale($booking);
        $siteName = SiteSetting::siteName();

        // phone-sized: it is read on a screen at the stable's gate far more than printed
        $mpdf = $this->factory->make([
            'format' => [100, 190],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font_size' => 10,
        ]);

        $mpdf->SetTitle(Locale::within($locale, fn () => __('booking_ticket.title')).' '.$booking->reference);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetCreator($siteName);
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($this->html($booking, $locale, $anonymous));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
