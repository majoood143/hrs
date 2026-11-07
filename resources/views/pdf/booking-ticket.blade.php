@php
    $align = $rtl ? 'right' : 'left';
    $slot = $booking->slot;
    $stable = $booking->stable;
    $done = $booking->status === \App\Enums\StableBookingStatus::Completed;
    // a name typed in Arabic inside an English ticket needs Cairo: the brand fonts may have no Arabic letters
    $v = fn (?string $text) => new \Illuminate\Support\HtmlString(preg_match('/\p{Arabic}/u', (string) $text) && ! $rtl
        ? '<span style="font-family: cairo;">'.$d($text).'</span>'
        : (string) $d($text));
    $place = collect([$stable?->address, $stable?->city?->name])->filter()->implode(', ');
    // each name on its own: one Arabic name would otherwise turn the whole list around
    $names = $anonymous ? '' : collect($booking->riders_data ?? [])->pluck('name')->filter()->map(fn ($name) => $v($name)->toHtml())->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('booking_ticket.title') }} {{ $booking->reference }}</title>
    <style>
        body { font-family: {{ $bodyFont }}; font-size: 9.5pt; color: #2b2b2b; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .head { text-align: center; padding: 6mm 8mm 3mm 8mm; }
        .site { font-family: {{ $headingFont }}; font-size: 15pt; font-weight: bold; color: {{ $primary }}; }
        .band { background-color: {{ $primary }}; color: #ffffff; padding: 3.5mm 8mm; }
        .band td { color: #ffffff; vertical-align: middle; }
        .band .title { font-family: {{ $headingFont }}; font-size: 13pt; font-weight: bold; }
        .pill { background-color: #ffffff; color: {{ $primary }}; font-weight: bold; font-size: 8.5pt; padding: 1mm 3mm; border-radius: 3mm; }
        .body { padding: 5mm 8mm 0 8mm; }
        .stable { font-family: {{ $headingFont }}; font-size: 15pt; font-weight: bold; color: #111111; text-align: center; }
        .offering { font-size: 10.5pt; color: #555555; text-align: center; margin-top: 1mm; }
        .qr { text-align: center; margin-top: 4mm; }
        .ref { text-align: center; font-size: 16pt; font-weight: bold; letter-spacing: 1pt; color: #111111; margin-top: 1mm; }
        .scan { text-align: center; font-size: 8pt; color: #666666; margin-top: 1mm; }
        .tear { border-top: 0.4mm dashed #c8c8c8; margin: 5mm 0 3mm 0; }
        table.facts { width: 100%; }
        table.facts td { padding: 1mm 0; vertical-align: top; text-align: {{ $align }}; }
        table.facts td.label { color: #777777; width: 30%; font-size: 8.5pt; }
        table.facts td.value { color: #1d1d1d; font-weight: bold; }
        .muted { color: #777777; font-weight: normal; }
        a.map { color: {{ $primary }}; text-decoration: none; font-weight: bold; }
        .foot { position: fixed; bottom: 4mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #8a8a8a; }
    </style>
</head>
<body>
    <div class="head">
        @if($logo)
            <img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}mm" height="{{ $logo['height'] }}mm" alt="{{ $siteName }}">
        @else
            <div class="site">{{ $siteName }}</div>
        @endif
    </div>

    <div class="band">
        <table width="100%"><tr>
            <td class="title">{{ __('booking_ticket.title') }}</td>
            <td style="text-align: {{ $rtl ? 'left' : 'right' }};"><span class="pill">{{ __('booking_ticket.status.'.($done ? 'completed' : 'confirmed')) }}</span></td>
        </tr></table>
    </div>

    <div class="body">
        <div class="stable">{{ $v($stable?->name) }}</div>
        <div class="offering">{{ $v($booking->offering?->name) }}</div>

        <div class="qr"><barcode code="{{ $qrUrl }}" type="QR" error="M" size="1.25" disableborder="1" /></div>
        <div class="ref"><span dir="ltr">{{ $booking->reference }}</span></div>
        <div class="scan">{{ __('booking_ticket.show_qr') }}</div>

        <div class="tear"></div>

        <table class="facts">
            <tr><td class="label">{{ __('booking_ticket.date') }}</td><td class="value">{{ $data['date'] }}</td></tr>
            <tr><td class="label">{{ __('booking_ticket.time') }}</td><td class="value">{{ $slot ? __('booking_ticket.time_range', ['start' => $slot->startsAt()->format('H:i'), 'end' => $slot->endsAt()->format('H:i')]) : '' }}</td></tr>
            <tr><td class="label">{{ __('booking_ticket.riders') }}</td><td class="value">{{ $data['riders'] }}@if($names !== '')<br><span class="muted">{!! $names !!}</span>@endif</td></tr>
            @if($slot?->trainer)
                <tr><td class="label">{{ __('booking_ticket.trainer') }}</td><td class="value">{{ $v($slot->trainer->name) }}</td></tr>
            @endif
            @if(! $anonymous && $booking->order?->customer_name)
                <tr><td class="label">{{ __('booking_ticket.customer') }}</td><td class="value">{{ $v($booking->order->customer_name) }}</td></tr>
            @endif
            <tr><td class="label">{{ __('booking_ticket.payment') }}</td><td class="value">{{ $data['payment'] }}</td></tr>
            @if($place !== '' || $stable?->map_link)
                <tr><td class="label">{{ __('booking_ticket.location') }}</td><td class="value">
                    @if($place !== ''){{ $v($place) }}<br>@endif
                    @if($stable?->map_link)<a class="map" href="{{ $stable->map_link }}">{{ __('booking_ticket.map') }}</a>@endif
                </td></tr>
            @endif
        </table>
    </div>

    <div class="foot">{{ $siteName }} · <span dir="ltr">{{ $siteUrl }}</span></div>
</body>
</html>
