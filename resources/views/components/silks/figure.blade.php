{{--
    The jacket and cap of one design (SilksDesign::paint()). The same drawing is the live preview,
    the downloaded SVG/PNG (with the logo, $caption lines and a QR code of $link around it) and the
    admin pattern preview. The
    designer script repaints it through the data-silks-* hooks. Always left-to-right: on an Arabic
    page the sleeves must not swap sides.
--}}
@props(['paint', 'uid' => 'silks', 'label' => '', 'caption' => [], 'footer' => null, 'logo' => null, 'link' => null, 'linkLabel' => null, 'standalone' => false])

@php
    use App\Support\Silks\SilksTemplate as T;

    // a downloaded file carries the site logo above the drawing (RacingPdf::siteLogo(): a data: URI, sized in mm)
    $logoHeight = 40;
    $logoWidth = $logo ? min(240, $logoHeight * $logo['width'] / max($logo['height'], 0.01)) : 0;
    $top = $logo ? $logoHeight + 28 : 0;
    $captionHeight = $caption ? 28 + count($caption) * 24 : 0;
    // a QR code that reopens the design (SilksLink), with a line saying so
    $qrSize = 104;
    $qr = $link ? \App\Support\Silks\SilksLink::qrRects($link, $qrSize) : '';
    $qrTop = $top + T::HEIGHT + $captionHeight + 14;
    $qrHeight = $qr !== '' ? 14 + $qrSize + 30 : 0;
    $height = $top + T::HEIGHT + $captionHeight + $qrHeight + ($footer ? 30 : 0);
    $rtl = app()->getLocale() === 'ar';
    $outline = 'fill="none" stroke="#1c1917" stroke-width="2.5" stroke-linejoin="round"';
@endphp

<svg @if($standalone) xmlns="http://www.w3.org/2000/svg" width="{{ T::WIDTH }}" height="{{ $height }}" @endif viewBox="0 0 {{ T::WIDTH }} {{ $height }}" role="img" aria-label="{{ $label }}" dir="ltr" data-silks-figure="" {{ $attributes }}>
    <title>{{ $label }}</title>
    <defs>
        @foreach(T::AREAS as $area)
            <clipPath id="{{ $uid }}-{{ $area }}"><path d="{{ T::clipPath($area) }}"/></clipPath>
        @endforeach
        <linearGradient id="{{ $uid }}-shade" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#000" stop-opacity="0.14"/>
            <stop offset="0.35" stop-color="#fff" stop-opacity="0.12"/>
            <stop offset="0.65" stop-color="#fff" stop-opacity="0"/>
            <stop offset="1" stop-color="#000" stop-opacity="0.14"/>
        </linearGradient>
    </defs>

    @if($caption || $logo || $qr !== '')
        <rect width="{{ T::WIDTH }}" height="{{ $height }}" fill="#ffffff"/>
    @endif

    @if($logo)
        <image href="{{ $logo['src'] }}" x="{{ (T::WIDTH - $logoWidth) / 2 }}" y="16" width="{{ $logoWidth }}" height="{{ $logoHeight }}" preserveAspectRatio="xMidYMid meet"/>
    @endif

    <g transform="translate(0 {{ $top }})">

    @foreach(['sleeve_left', 'sleeve_right'] as $side)
        <g transform="{{ T::PLACEMENT[$side] }}">
            @include('components.silks.area', ['area' => 'sleeves', 'paint' => $paint['sleeves'], 'uid' => $uid])
            <path d="{{ T::SLEEVE }}" {!! $outline !!}/>
            <path d="{{ T::CUFF }}" fill="none" stroke="#1c1917" stroke-width="1.5" stroke-opacity="0.55"/>
        </g>
    @endforeach

    <g transform="{{ T::PLACEMENT['body'] }}">
        @include('components.silks.area', ['area' => 'body', 'paint' => $paint['body'], 'uid' => $uid])
        <path d="{{ T::BODY }}" {!! $outline !!}/>
        <path d="{{ T::COLLAR }}" fill="{{ $paint['body']['base'] }}" data-silks-base="body" stroke="#1c1917" stroke-width="2" stroke-linejoin="round"/>
    </g>

    <g transform="{{ T::PLACEMENT['cap'] }}">
        <path d="{{ T::CAP_PEAK }}" fill="{{ $paint['cap']['base'] }}" data-silks-base="cap" stroke="#1c1917" stroke-width="2.5" stroke-linejoin="round"/>
        @include('components.silks.area', ['area' => 'cap', 'paint' => $paint['cap'], 'uid' => $uid])
        <path d="{{ T::CAP_CROWN }}" {!! $outline !!}/>
        <circle cx="70" cy="3" r="5" fill="{{ $paint['cap']['base'] }}" data-silks-base="cap" stroke="#1c1917" stroke-width="2"/>
    </g>

    </g>

    @if($qr !== '')
        <g transform="translate({{ (T::WIDTH - $qrSize) / 2 }} {{ $qrTop }})" fill="#000000" shape-rendering="crispEdges">{!! $qr !!}</g>
    @endif

    @if($caption || $footer || $qr !== '')
        <g font-family="Cairo, 'Segoe UI', Tahoma, Arial, sans-serif" text-anchor="middle" fill="#1c1917" @if($rtl) direction="rtl" @endif>
            @foreach($caption as $i => $line)
                <text x="{{ T::WIDTH / 2 }}" y="{{ $top + T::HEIGHT + 24 + $i * 24 }}" font-size="16">{{ $line }}</text>
            @endforeach
            @if($qr !== '' && $linkLabel)
                <text x="{{ T::WIDTH / 2 }}" y="{{ $qrTop + $qrSize + 22 }}" font-size="12" fill="#44403c">{{ $linkLabel }}</text>
            @endif
            @if($footer)
                <text x="{{ T::WIDTH / 2 }}" y="{{ $height - 12 }}" font-size="12" fill="#78716c">{{ $footer }}</text>
            @endif
        </g>
    @endif
</svg>
