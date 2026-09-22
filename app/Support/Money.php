<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\HtmlString;

class Money
{
    /** 11025 baisa as "OMR 11.025" (the site's currency code, three decimals). */
    public static function format(int $baisa, ?string $currency = null): string
    {
        return ($currency ?? SiteSetting::currency()['code']).' '.number_format($baisa / 1000, 3);
    }

    /**
     * Same as format(), but as HTML: the amount next to the uploaded currency icon (SVG, General
     * Settings) instead of the code/symbol text, when one is uploaded and the currency matches the
     * site's — an order snapshotted in a different currency always falls back to plain code text.
     * Returned as an HtmlString so Blade (`{{ }}`) and Filament (Stat/TextColumn) render it unescaped.
     */
    public static function formatHtml(int $baisa, ?string $currency = null): HtmlString
    {
        $site = SiteSetting::currency();
        $code = $currency ?? $site['code'];
        $amount = number_format($baisa / 1000, 3);

        if ($code === $site['code'] && $site['icon_url']) {
            return new HtmlString(
                '<img src="'.e($site['icon_url']).'" alt="'.e($code).'" class="inline-block h-3.5 w-3.5 object-contain align-middle">'
                .' '.e($amount)
            );
        }

        return new HtmlString(e($code).' '.e($amount));
    }

    /**
     * Same as formatHtml(), for mPDF: $icon is RacingPdf::currencyIcon()'s data-URI array (mPDF can't
     * fetch our own site's URLs over HTTP, so the web icon_url can't be used here), or null to fall
     * back to plain code text.
     *
     * @param  ?array{src: string, width: float, height: float}  $icon
     */
    public static function formatPdfHtml(int $baisa, ?array $icon, ?string $currency = null): HtmlString
    {
        $amount = number_format($baisa / 1000, 3);

        if ($icon) {
            return new HtmlString(
                '<img src="'.e($icon['src']).'" style="height: '.$icon['height'].'mm; width: '.$icon['width'].'mm; vertical-align: middle;">'
                .' '.e($amount)
            );
        }

        return new HtmlString(e($currency ?? SiteSetting::currency()['code']).' '.e($amount));
    }

    /** 11025 baisa as "11.025", for a spreadsheet. */
    public static function plain(int $baisa): string
    {
        return number_format($baisa / 1000, 3, '.', '');
    }
}
