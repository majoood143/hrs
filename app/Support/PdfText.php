<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class PdfText
{
    /**
     * Keep a value's own reading direction inside a document of the other direction.
     *
     * Without it, Latin text in an Arabic PDF ("(OR 62) 56.0", "Good (d)") comes out with its brackets
     * and words flipped, and Arabic in an English one loses its punctuation. Text that already
     * matches the document (or has no letters at all, like "56.0") is left alone.
     */
    public static function dir(?string $text, bool $rtl): HtmlString
    {
        $text = trim((string) $text);
        $escaped = e($text);

        if ($text === '') {
            return new HtmlString('');
        }

        $arabic = preg_match('/\p{Arabic}/u', $text) === 1;

        if ($rtl && ! $arabic && preg_match('/[\p{Latin}()\[\]]/u', $text) === 1) {
            return new HtmlString('<span dir="ltr">' . $escaped . '</span>');
        }

        if (! $rtl && $arabic) {
            return new HtmlString('<span dir="rtl">' . $escaped . '</span>');
        }

        return new HtmlString($escaped);
    }
}
