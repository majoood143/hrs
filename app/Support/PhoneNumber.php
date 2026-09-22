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
        $digits = preg_replace('/\D+/', '', (string) $raw);

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
}
