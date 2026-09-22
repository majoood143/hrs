<?php

namespace App\Support;

use App\Models\SiteSetting;

class Money
{
    /** 11025 baisa as "OMR 11.025" (the site's currency code, three decimals). */
    public static function format(int $baisa, ?string $currency = null): string
    {
        return ($currency ?? SiteSetting::currency()['code']).' '.number_format($baisa / 1000, 3);
    }

    /** 11025 baisa as "11.025", for a spreadsheet. */
    public static function plain(int $baisa): string
    {
        return number_format($baisa / 1000, 3, '.', '');
    }
}
