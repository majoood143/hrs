@php
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $code = $order->currency ?: 'OMR';
    $money = fn ($amount) => $code.' '.number_format((float) $amount, 3);
    $subtotal = (float) $order->price + (float) $order->fee_amount;
    $vat = (float) $order->vat_on_price + (float) $order->vat_on_fee;
    $rate = rtrim(rtrim(number_format((float) $order->vat_rate, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: cairo; font-size: 10pt; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 20pt; color: #000000; margin: 0; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 6mm; }
        .brand td { padding: 0 0 3mm 0; vertical-align: middle; }
        .site { font-size: 15pt; font-weight: bold; color: #000000; }
        .muted { color: #666666; }
        .small { font-size: 8pt; }
        .scan { font-size: 6pt; color: #666666; text-align: center; }
        table.facts { width: 100%; margin-bottom: 6mm; }
        table.facts td { padding: 1mm 0; vertical-align: top; text-align: {{ $align }}; }
        table.facts td.label { color: #666666; width: 32%; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 2mm 3mm; border-bottom: 0.4mm solid #888888; font-size: 9pt; }
        table.lines th.amount, table.lines td.amount { text-align: {{ $opposite }}; }
        table.lines td { padding: 2mm 3mm; border-bottom: 0.2mm solid #dddddd; text-align: {{ $align }}; }
        table.lines tr.sum td { border-top: 0.4mm solid #888888; font-weight: bold; }
        table.lines tr.total td { background-color: #f2f2f2; font-size: 12pt; font-weight: bold; color: #000000; border-top: 0.6mm solid #000000; }
        .note { margin-top: 8mm; padding: 3mm; border: 0.3mm dashed #aaaaaa; font-size: 8.5pt; color: #555555; }
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
            <h1>{{ __('receipt.title') }}</h1>
        </td>
        <td width="28mm">
            <div style="text-align: center;"><barcode code="{{ $qrUrl }}" type="QR" error="M" size="0.7" disableborder="1" /></div>
            <div class="scan">{{ __('receipt.scan') }}</div>
        </td>
    </tr></table>

    <table class="facts">
        <tr><td class="label">{{ __('orders.receipt_number') }}</td><td><span dir="ltr">{{ $order->receipt_number }}</span></td></tr>
        <tr><td class="label">{{ __('receipt.date') }}</td><td>{{ $d($order->paid_at?->locale($locale)->translatedFormat('j F Y, H:i')) }}</td></tr>
        <tr><td class="label">{{ __('orders.order_number') }}</td><td><span dir="ltr">{{ $order->order_number }}</span></td></tr>
        <tr><td class="label">{{ __('receipt.customer') }}</td><td>{{ $d($order->customer_name) }}@if($order->customer_phone) <span dir="ltr" class="muted">&nbsp;·&nbsp;+{{ $order->customer_phone }}</span>@endif</td></tr>
        @if($order->payment_method)
            <tr><td class="label">{{ __('receipt.paid_with') }}</td><td>{{ $order->payment_method->label() }}@if($order->payment_reference) <span dir="ltr" class="muted">&nbsp;·&nbsp;{{ $order->payment_reference }}</span>@endif</td></tr>
        @endif
        @if($vatNumber !== '')
            <tr><td class="label">{{ __('receipt.vat_number') }}</td><td><span dir="ltr">{{ $vatNumber }}</span></td></tr>
        @endif
    </table>

    <table class="lines">
        <tr><th>{{ __('receipt.description') }}</th><th class="amount">{{ __('receipt.amount') }}</th></tr>
        <tr><td>{{ $d($serviceName) }}</td><td class="amount"><span dir="ltr">{{ $money($order->price) }}</span></td></tr>
        @if((float) $order->fee_amount > 0)
            <tr><td>{{ __('orders.service_fee') }}</td><td class="amount"><span dir="ltr">{{ $money($order->fee_amount) }}</span></td></tr>
        @endif
        <tr class="sum"><td>{{ __('receipt.subtotal') }}</td><td class="amount"><span dir="ltr">{{ $money($subtotal) }}</span></td></tr>
        @if($vat > 0)
            <tr><td>{{ __('receipt.vat_on_service', ['rate' => $rate]) }}</td><td class="amount"><span dir="ltr">{{ $money($order->vat_on_price) }}</span></td></tr>
            @if((float) $order->vat_on_fee > 0)
                <tr><td>{{ __('receipt.vat_on_fee', ['rate' => $rate]) }}</td><td class="amount"><span dir="ltr">{{ $money($order->vat_on_fee) }}</span></td></tr>
            @endif
        @endif
        <tr class="total"><td>{{ __('receipt.total_paid') }}</td><td class="amount"><span dir="ltr">{{ $money($order->total) }}</span></td></tr>
    </table>

    @if((float) $order->fee_amount > 0)
        <div class="note">{{ __('receipt.fee_note') }}</div>
    @endif
</body>
</html>
