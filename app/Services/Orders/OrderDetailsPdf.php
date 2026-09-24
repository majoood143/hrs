<?php

namespace App\Services\Orders;

use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\Locale;
use App\Support\OrderAnswers;
use App\Support\PdfText;
use Mpdf\Output\Destination;

/**
 * A printable copy of the customer's order page: status, amounts, what they asked for, documents
 * and progress. Unlike the receipt it exists for every order, paid or not. Plain tables for mPDF.
 */
class OrderDetailsPdf
{
    public function __construct(
        private readonly MpdfFactory $factory,
        private readonly OrderReceiptPdf $receipts,
    ) {}

    public function filename(ServiceOrder $order): string
    {
        return 'order-'.$order->order_number.'.pdf';
    }

    public function html(ServiceOrder $order, ?string $locale = null): string
    {
        $locale = $this->receipts->locale($order, $locale);

        return Locale::within($locale, function () use ($order, $locale) {
            $rtl = $locale === 'ar';
            $order->loadMissing(['service', 'submission.form', 'documents', 'refunds']);

            return view('pdf.order-details', [
                'order' => $order,
                'serviceName' => $order->service?->localizedName() ?? __('orders.service'),
                'answers' => OrderAnswers::for($order),
                'events' => $order->events()->where('is_public', true)->get(),
                'locale' => $locale,
                'rtl' => $rtl,
                'siteName' => SiteSetting::siteName(),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'currencyIcon' => app(RacingPdf::class)->currencyIcon(),
                'qrUrl' => route('orders.show', $order->order_number).'?lang='.$locale,
                'title' => __('order_pdf.title').' '.$order->order_number,
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });
    }

    public function render(ServiceOrder $order, ?string $locale = null): string
    {
        $locale = $this->receipts->locale($order, $locale);
        $siteName = SiteSetting::siteName();

        $mpdf = $this->factory->make([
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'default_font_size' => 10,
        ]);

        $mpdf->SetTitle(Locale::within($locale, fn () => __('order_pdf.title')).' '.$order->order_number);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetCreator($siteName);
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($this->html($order, $locale));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
