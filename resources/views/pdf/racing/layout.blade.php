@php $align = $rtl ? 'right' : 'left'; @endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: cairo; font-size: 9pt; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        a { color: #000000; text-decoration: none; }
        h1 { font-size: 19pt; color: #000000; margin: 0 0 1.5mm 0; }
        h2 { font-size: 12pt; color: #000000; margin: 6mm 0 2mm 0; padding-bottom: 1mm; border-bottom: 0.3mm solid #999999; }
        h3 { font-size: 10pt; color: #000000; margin: 4mm 0 1.5mm 0; }
        p { margin: 0 0 1.5mm 0; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 5mm; }
        .brand td { padding: 0 0 2mm 0; vertical-align: middle; }
        .site { font-size: 14pt; font-weight: bold; color: #000000; }
        .scan { font-size: 6pt; color: #666666; text-align: center; padding-bottom: 1mm; }
        .eyebrow { font-size: 8pt; color: #555555; text-transform: uppercase; letter-spacing: 0.5pt; }
        .muted { color: #666666; }
        .small { font-size: 8pt; }
        .facts td { padding: 0.6mm 4mm 0.6mm 0; vertical-align: top; }
        .facts td.label { color: #666666; }
        table.box { width: 100%; border: 0.4mm solid #bbbbbb; background-color: #f2f2f2; }
        table.box td { text-align: center; padding: 2.5mm 4mm; }
        table.box .value { font-size: 20pt; font-weight: bold; color: #000000; }
        table.grid { border-collapse: collapse; width: 100%; font-size: 8pt; margin-bottom: 3mm; }
        table.grid th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 1.3mm 1.6mm; border-bottom: 0.4mm solid #888888; font-size: 7.5pt; }
        table.grid td { padding: 1.2mm 1.6mm; border-bottom: 0.2mm solid #dddddd; vertical-align: top; text-align: {{ $align }}; }
        .empty { border: 0.3mm dashed #aaaaaa; padding: 4mm; text-align: center; color: #666666; }
    </style>
</head>
<body>
    <table class="brand"><tr>
        <td>
            @if($logo)
                <img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}mm" height="{{ $logo['height'] }}mm" alt="{{ $siteName }}">
            @else
                <div class="site">{{ $siteName }}</div>
            @endif
            <div class="eyebrow">{{ __('racing.eyebrow') }}</div>
        </td>
        <td width="28mm">
            {{-- opens this page online; mPDF draws the code itself --}}
            <div style="text-align: center;"><barcode code="{{ $qrUrl }}" type="QR" error="M" size="0.7" disableborder="1" /></div>
            <div class="scan">{{ __('racing.pdf.scan') }}</div>
        </td>
    </tr></table>

    @yield('content')
</body>
</html>
