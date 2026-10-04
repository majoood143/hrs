{{--
    A racing silks design on one A4 page (SilksController::pdf). The drawing is a PNG made by the
    visitor's browser (mPDF cannot clip SVG shapes); the rest is built from the design. Unlike the
    racing PDFs this one is in colour: the colours are the point.
--}}
@php
    $align = $rtl ? 'right' : 'left';
    // fit the drawing in a 95 × 120 mm box
    $imageWidth = min(95, 120 / max($imageRatio, 0.01));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('silks.pdf_title') }}</title>
    <style>
        body { font-family: cairo; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 20pt; color: #000000; margin: 1mm 0 0 0; }
        .subtitle { font-size: 9pt; color: #666666; margin: 0; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 5mm; }
        .brand td { padding: 0 0 2mm 0; vertical-align: middle; text-align: {{ $align }}; }
        .site { font-size: 14pt; font-weight: bold; color: #000000; }
        .drawing { text-align: center; margin: 2mm 0 4mm 0; }
        .description { padding: 3mm 4mm; border: 0.3mm solid #cccccc; background-color: #f7f7f7; font-size: 12pt; color: #000000; text-align: center; }
        .label { font-size: 8pt; color: #666666; }
        table.grid { border-collapse: collapse; width: 100%; margin-top: 5mm; }
        table.grid th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 1.6mm 2mm; border-bottom: 0.4mm solid #888888; }
        table.grid td { padding: 1.6mm 2mm; border-bottom: 0.2mm solid #dddddd; vertical-align: middle; text-align: {{ $align }}; }
        /* mPDF does not paint a sized div in a cell: a swatch is a one-cell table (outlined, so white shows) */
        table.grid td.chip { width: 7mm; padding-right: 0; padding-left: 0; }
        table.swatch { border-collapse: collapse; }
        table.swatch td { width: 5mm; height: 5mm; padding: 0; border: 0.25mm solid #888888; }
        table.open { width: 100%; margin-top: 5mm; border-collapse: collapse; }
        table.open td { vertical-align: middle; text-align: {{ $align }}; font-size: 9pt; color: #333333; }
        table.open td.qr { width: 24mm; padding-{{ $rtl ? 'left' : 'right' }}: 4mm; }
        .url { margin-top: 1.5mm; font-size: 8pt; text-align: {{ $align }}; }
        .url a { color: #0645ad; }
        .note { margin-top: 4mm; padding: 2.5mm; border: 0.3mm dashed #aaaaaa; font-size: 8pt; color: #555555; }
        .footer { margin-top: 5mm; font-size: 8pt; color: #888888; text-align: center; }
    </style>
</head>
<body>
    <table class="brand"><tr><td>
        @if($logo)
            <img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}mm" height="{{ $logo['height'] }}mm" alt="{{ $siteName }}">
        @else
            <div class="site">{{ $siteName }}</div>
        @endif
        <h1>{{ __('silks.pdf_title') }}</h1>
        <p class="subtitle">{{ __('silks.created_on', ['date' => now()->locale($locale)->translatedFormat('j F Y')]) }}</p>
    </td></tr></table>

    <div class="drawing">
        <img src="{{ $image }}" width="{{ round($imageWidth, 1) }}mm" height="{{ round($imageWidth * $imageRatio, 1) }}mm" alt="{{ $description }}">
    </div>

    <div class="description">
        <div class="label">{{ __('silks.description') }}</div>
        {{ $d($description) }}
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('silks.part') }}</th>
                <th>{{ __('silks.pattern') }}</th>
                <th colspan="2">{{ __('silks.main_colour') }}</th>
                <th colspan="2">{{ __('silks.pattern_colour') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $area => $part)
                <tr>
                    <td><b>{{ __('silks.areas.'.$area) }}</b></td>
                    <td>{{ $d($part['pattern']) }}</td>
                    <td class="chip"><table class="swatch"><tr><td style="background-color: {{ $part['base']['hex'] }};"></td></tr></table></td>
                    <td>{{ $d($part['base']['name']) }} <span class="label" dir="ltr">{{ $part['base']['hex'] }}</span></td>
                    @if($part['accent'])
                        <td class="chip"><table class="swatch"><tr><td style="background-color: {{ $part['accent']['hex'] }};"></td></tr></table></td>
                        <td>{{ $d($part['accent']['name']) }} <span class="label" dir="ltr">{{ $part['accent']['hex'] }}</span></td>
                    @else
                        <td class="chip"></td>
                        <td class="label">—</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($link)
        {{-- the QR code and the full address both reopen this exact design, in this PDF's language --}}
        <table class="open"><tr>
            <td class="qr"><barcode code="{{ $link }}" type="QR" error="M" size="0.8" disableborder="1" /></td>
            <td>{{ __('silks.pdf_scan') }}</td>
        </tr></table>
        {{-- on its own line: an unbreakable address inside a table cell makes mPDF shrink the whole table --}}
        <div class="url"><a href="{{ $link }}">{{ $d($link) }}</a></div>
    @endif

    <div class="note">{{ __('silks.disclaimer') }}</div>

    <div class="footer">{{ __('silks.designed_on', ['site' => $siteName]) }}</div>
</body>
</html>
