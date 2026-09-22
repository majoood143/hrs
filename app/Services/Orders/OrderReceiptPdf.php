<?php

namespace App\Services\Orders;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\Locale;
use App\Support\PdfText;
use Mpdf\Output\Destination;

/**
 * The payment receipt of a paid order: price, service fee, VAT and total, in the customer's
 * language (Arabic laid out right to left). Plain table markup because mPDF does not read Tailwind.
 */
class OrderReceiptPdf
{
    public function __construct(private readonly MpdfFactory $factory) {}

    public function available(ServiceOrder $order): bool
    {
        return $order->isPaid() && filled($order->receipt_number);
    }

    public function filename(ServiceOrder $order): string
    {
        return 'receipt-'.$order->receipt_number.'.pdf';
    }

    /** The receipt language: the one asked for if we have it, else the language the customer ordered in. */
    public function locale(ServiceOrder $order, ?string $requested = null): string
    {
        return in_array($requested, array_keys(config('languages.available', [])), true) ? $requested : ($order->locale ?: 'en');
    }

    /** The receipt as HTML (what the PDF is made from). */
    public function html(ServiceOrder $order, ?string $locale = null): string
    {
        $locale = $this->locale($order, $locale);

        // the views and the service name read the app locale, so the whole render happens in the receipt's language
        return Locale::within($locale, function () use ($order, $locale) {
            $rtl = $locale === 'ar';
            $url = route('orders.show', $order->order_number).'?lang='.$locale;

            return view('pdf.receipt', [
                'order' => $order->loadMissing('service'),
                'serviceName' => $order->service?->localizedName() ?? __('orders.service'),
                'locale' => $locale,
                'rtl' => $rtl,
                'siteName' => SiteSetting::siteName(),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'vatNumber' => (string) SiteSetting::get('vat.registration_number', ''),
                'qrUrl' => $url,
                'title' => __('receipt.title').' '.$order->receipt_number,
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });
    }

    public function render(ServiceOrder $order, ?string $locale = null): string
    {
        $locale = $this->locale($order, $locale);
        $siteName = SiteSetting::siteName();

        $mpdf = $this->factory->make([
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'default_font_size' => 10,
        ]);

        $mpdf->SetTitle(Locale::within($locale, fn () => __('receipt.title')).' '.$order->receipt_number);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetCreator($siteName);
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($this->html($order, $locale));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
