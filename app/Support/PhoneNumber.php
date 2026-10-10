<?php

namespace App\Support;

/**
 * One spelling for a phone number, so the same customer is recognised however they typed it
 * (order forms, SMS, and the phone login later): digits only, with the country code.
 * A bare 8-digit number is an Omani one.
 */
class PhoneNumber
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) self::toAscii($raw));

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 8) {
            $digits = '968'.$digits;
        }

        return $digits;
    }

    /**
     * The number as typed, but in plain ASCII: Arabic-Indic and Persian digits (an Arabic keyboard)
     * become 0-9, the invisible direction marks a pasted number from WhatsApp or the contacts app
     * carries are dropped, and non-breaking spaces become spaces. Nothing else changes.
     */
    public static function toAscii(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = strtr($raw, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            "\u{00A0}" => ' ', "\u{202F}" => ' ',
        ]);

        return trim(preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $raw));
    }
}
