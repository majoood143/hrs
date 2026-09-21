<?php

namespace Tests\Unit;

use App\Support\PdfText;
use PHPUnit\Framework\TestCase;

class PdfTextTest extends TestCase
{
    public function test_latin_text_keeps_its_direction_inside_an_arabic_document(): void
    {
        $this->assertSame('<span dir="ltr">58.0 (OR 75)</span>', (string) PdfText::dir('58.0 (OR 75)', true));
        $this->assertSame('<span dir="ltr">Good (d)</span>', (string) PdfText::dir('Good (d)', true));
    }

    public function test_arabic_text_keeps_its_direction_inside_an_english_document(): void
    {
        $this->assertSame('<span dir="rtl">القوس (عمان)</span>', (string) PdfText::dir('القوس (عمان)', false));
    }

    public function test_text_that_already_matches_or_has_no_letters_is_left_alone(): void
    {
        $this->assertSame('القوس', (string) PdfText::dir('القوس', true));
        $this->assertSame('Al Qous', (string) PdfText::dir('Al Qous', false));
        $this->assertSame('56.0', (string) PdfText::dir('56.0', true));
        $this->assertSame('19-11-2021', (string) PdfText::dir('19-11-2021', true));
    }

    public function test_it_escapes_and_copes_with_empty_values(): void
    {
        $this->assertSame('<span dir="ltr">&lt;b&gt;x&lt;/b&gt; (1)</span>', (string) PdfText::dir('<b>x</b> (1)', true));
        $this->assertSame('', (string) PdfText::dir(null, true));
        $this->assertSame('', (string) PdfText::dir('  ', false));
    }
}
