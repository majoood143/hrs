<?php

namespace App\Services\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * An mPDF configured for this site: Cairo (the site's own font, covering Latin and Arabic, so one
 * font serves both languages) with proper Arabic shaping, and a temp folder inside storage. mPDF
 * is used because it shapes Arabic and lays out right-to-left correctly. It cannot use variable
 * fonts, hence the static Cairo files in resources/fonts/cairo.
 */
class MpdfFactory
{
    /**
     * @param  array<string, mixed>  $overrides  mPDF config (format, margins, font size...) on top of the defaults
     */
    public function make(array $overrides = []): Mpdf
    {
        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        return new Mpdf($overrides + [
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, resource_path('fonts/cairo')],
            'fontdata' => $fontData + [
                // useOTL / useKashida turn on proper Arabic shaping
                'cairo' => ['R' => 'Cairo-Regular.ttf', 'B' => 'Cairo-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'cairo',
            'default_font_size' => 9,
            'autoArabic' => true,
        ]);
    }
}
