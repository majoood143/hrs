<?php

namespace App\Services\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * An mPDF configured for this site: Cairo (the site's own font, covering Latin and Arabic, so one
 * font serves both languages) with proper Arabic shaping, and a temp folder inside storage. mPDF
 * is used because it shapes Arabic and lays out right-to-left correctly. It cannot use variable
 * fonts, hence the static Cairo files in resources/fonts/cairo (and the brand fonts' in resources/fonts/brand).
 */
class MpdfFactory
{
    /** General Settings → Branding's font keys => their static files in resources/fonts/brand (Cairo is in fonts/cairo). */
    public const BRAND_FONTS = [
        'inter' => ['Inter_400Regular.ttf', 'Inter_600SemiBold.ttf'],
        'poppins' => ['Poppins_400Regular.ttf', 'Poppins_600SemiBold.ttf'],
        'roboto' => ['Roboto_400Regular.ttf', 'Roboto_700Bold.ttf'],
        'nunito' => ['Nunito_400Regular.ttf', 'Nunito_700Bold.ttf'],
        'montserrat' => ['Montserrat_400Regular.ttf', 'Montserrat_600SemiBold.ttf'],
        'tajawal' => ['Tajawal_400Regular.ttf', 'Tajawal_700Bold.ttf'],
        'almarai' => ['Almarai_400Regular.ttf', 'Almarai_700Bold.ttf'],
        'fraunces' => ['Fraunces_400Regular.ttf', 'Fraunces_600SemiBold.ttf'],
        'playfair-display' => ['PlayfairDisplay_400Regular.ttf', 'PlayfairDisplay_600SemiBold.ttf'],
        'merriweather' => ['Merriweather_400Regular.ttf', 'Merriweather_700Bold.ttf'],
    ];

    /** The brand fonts that also have Arabic letters. */
    public const ARABIC_FONTS = ['cairo', 'tajawal', 'almarai'];

    /**
     * The mPDF family for a Branding font key: Cairo for an unknown one, and in Arabic for one
     * without Arabic letters (like the app's fonts.ts).
     */
    public static function brandFont(string $key, bool $arabic = false): string
    {
        if ($key !== 'cairo' && (! isset(self::BRAND_FONTS[$key]) || ($arabic && ! in_array($key, self::ARABIC_FONTS, true)))) {
            return 'cairo';
        }

        return str_replace('-', '', $key);
    }

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
            'fontDir' => [...$fontDirs, resource_path('fonts/cairo'), resource_path('fonts/brand')],
            'fontdata' => $fontData + [
                // useOTL / useKashida turn on proper Arabic shaping
                'cairo' => ['R' => 'Cairo-Regular.ttf', 'B' => 'Cairo-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ] + collect(self::BRAND_FONTS)->mapWithKeys(fn (array $files, string $key) => [str_replace('-', '', $key) => ['R' => $files[0], 'B' => $files[1]]
                + (in_array($key, self::ARABIC_FONTS, true) ? ['useOTL' => 0xFF, 'useKashida' => 75] : [])])->all(),
            'default_font' => 'cairo',
            'default_font_size' => 9,
            'autoArabic' => true,
        ]);
    }
}
