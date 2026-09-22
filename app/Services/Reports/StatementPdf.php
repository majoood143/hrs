<?php

namespace App\Services\Reports;

use App\Enums\PaymentGateway;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\Locale;
use App\Support\PdfText;
use Mpdf\Output\Destination;

/**
 * The fee and commission statement as a PDF (A4, English or Arabic): what was collected, what the
 * client owes us, and the orders behind it. It is what gets sent to the client to settle up.
 */
class StatementPdf
{
    public function __construct(private readonly MpdfFactory $factory) {}

    public function filename(IncomeStatement $statement): string
    {
        return 'income-statement-'.$statement->from->toDateString().'_'.$statement->to->toDateString().'.pdf';
    }

    public function html(IncomeStatement $statement, string $locale): string
    {
        return Locale::within($locale, function () use ($statement, $locale) {
            $rtl = $locale === 'ar';

            return view('pdf.statement', [
                'statement' => $statement,
                'totals' => $statement->totals(),
                'services' => $statement->byService(),
                'days' => $statement->byDay()->filter(fn (array $day) => $day['orders'] > 0),
                'rows' => $statement->rows(),
                'serviceName' => $statement->serviceId ? (Service::find($statement->serviceId)?->localizedName() ?? '#'.$statement->serviceId) : null,
                'gatewayName' => $statement->gateway ? (PaymentGateway::tryFrom($statement->gateway)?->label() ?? $statement->gateway) : null,
                'lines' => app(StatementCsv::class)->summaryLines($statement->totals()),
                'currency' => SiteSetting::currency()['code'],
                'currencyIcon' => app(RacingPdf::class)->currencyIcon(),
                'vatNumber' => (string) SiteSetting::get('vat.registration_number', ''),
                'logo' => app(RacingPdf::class)->siteLogo(),
                'siteName' => SiteSetting::siteName(),
                'locale' => $locale,
                'rtl' => $rtl,
                'title' => __('statement.title'),
                'd' => fn (?string $text) => PdfText::dir($text, $rtl),
            ])->render();
        });
    }

    public function render(IncomeStatement $statement, string $locale): string
    {
        $mpdf = $this->factory->make([
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 6,
            'default_font_size' => 9,
        ]);

        $title = Locale::within($locale, fn () => __('statement.title'));
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor(SiteSetting::siteName());
        $mpdf->SetCreator(SiteSetting::siteName());
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetHTMLFooter(Locale::within($locale, fn () => '<div style="font-family: cairo; font-size: 7pt; color: #666666; text-align: center; border-top: 0.2mm solid #cccccc; padding-top: 1mm;">'.e(__('statement.page')).' {PAGENO} / {nbpg}</div>'));

        // a month of orders is a long table, and mPDF parses with regular expressions
        @ini_set('pcre.backtrack_limit', '5000000');

        $mpdf->WriteHTML($this->html($statement, $locale));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
