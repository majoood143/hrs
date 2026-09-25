<?php

namespace App\Services\Exports;

use App\Models\SiteSetting;
use App\Services\Pdf\MpdfFactory;
use App\Services\Racing\RacingPdf;
use App\Support\PdfText;
use Mpdf\Output\Destination;

/**
 * An export as a PDF with the site logo, in the language it is rendered in (call it inside
 * Locale::within). Wide tables go landscape. A list stops at MAX_ROWS (mPDF slows down badly on
 * long tables); the PDF says so and the CSV has every row.
 */
class ExportPdf
{
    public const MAX_ROWS = 1000;

    public function __construct(private readonly MpdfFactory $factory) {}

    public function html(ExportDocument $document): string
    {
        $rtl = app()->getLocale() === 'ar';
        $rows = [];

        foreach ($document->rows($document->layout === ExportDocument::TABLE ? self::MAX_ROWS : null) as $row) {
            $rows[] = $row;
        }

        return view('pdf.export', [
            'document' => $document,
            'rows' => $rows,
            'truncated' => $document->layout === ExportDocument::TABLE && $document->total > count($rows),
            'columns' => count($document->headings),
            'logo' => app(RacingPdf::class)->siteLogo(),
            'siteName' => SiteSetting::siteName(),
            'locale' => app()->getLocale(),
            'rtl' => $rtl,
            'd' => fn (?string $text) => PdfText::dir($text, $rtl),
        ])->render();
    }

    public function render(ExportDocument $document): string
    {
        $columns = count($document->headings);

        $mpdf = $this->factory->make([
            'format' => $columns > 6 ? 'A4-L' : 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 6,
            'default_font_size' => $columns > 10 ? 7 : 9,
        ]);

        $mpdf->SetTitle($document->title);
        $mpdf->SetAuthor(SiteSetting::siteName());
        $mpdf->SetCreator(SiteSetting::siteName());
        $mpdf->SetDirectionality(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetHTMLFooter('<div style="font-family: cairo; font-size: 7pt; color: #666666; text-align: center; border-top: 0.2mm solid #cccccc; padding-top: 1mm;">'
            .e(SiteSetting::siteName()).' &middot; '.e(__('admin_export.page')).' {PAGENO} / {nbpg}</div>');

        // a long table is a lot of HTML, and mPDF parses it with regular expressions
        @ini_set('pcre.backtrack_limit', '5000000');

        $mpdf->WriteHTML($this->html($document));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
