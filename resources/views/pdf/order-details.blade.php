@php
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $code = $order->currency ?: \App\Models\SiteSetting::currency()['code'];
    // the uploaded currency icon only stands for the site's own currency
    $icon = $code === \App\Models\SiteSetting::currency()['code'] ? $currencyIcon : null;
    $money = fn ($amount) => \App\Support\Money::formatPdfHtml((int) round((float) $amount * 1000), $icon, $code);
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
        h2 { font-size: 12pt; color: #000000; margin: 7mm 0 2mm 0; padding-bottom: 1mm; border-bottom: 0.3mm solid #888888; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 6mm; }
        .brand td { padding: 0 0 3mm 0; vertical-align: middle; }
        .site { font-size: 15pt; font-weight: bold; color: #000000; }
        .muted { color: #666666; }
        .small { font-size: 8pt; }
        .scan { font-size: 6pt; color: #666666; text-align: center; }
        table.status { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin-bottom: 5mm; }
        table.status td { width: 50%; padding: 3mm 4mm; background-color: #f2f2f2; border: 0.3mm solid #cccccc; text-align: {{ $align }}; }
        table.status .cap { font-size: 8pt; color: #666666; }
        table.status .val { font-size: 13pt; font-weight: bold; color: #000000; }
        table.facts { width: 100%; }
        table.facts td { padding: 1mm 0; vertical-align: top; text-align: {{ $align }}; }
        table.facts td.label { color: #666666; width: 32%; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 2mm 3mm; border-bottom: 0.4mm solid #888888; font-size: 9pt; }
        table.lines th.amount, table.lines td.amount { text-align: {{ $opposite }}; }
        table.lines td { padding: 2mm 3mm; border-bottom: 0.2mm solid #dddddd; text-align: {{ $align }}; vertical-align: top; }
        table.lines tr.total td { background-color: #f2f2f2; font-size: 12pt; font-weight: bold; color: #000000; border-top: 0.6mm solid #000000; }
        table.lines td.label { color: #666666; width: 35%; }
        .alert { margin-bottom: 5mm; padding: 3mm; border: 0.4mm solid #000000; }
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
            <h1>{{ __('order_pdf.title') }}</h1>
            <div class="muted"><span dir="ltr">{{ $order->order_number }}</span></div>
        </td>
        <td width="28mm">
            <div style="text-align: center;"><barcode code="{{ $qrUrl }}" type="QR" error="M" size="0.7" disableborder="1" /></div>
            <div class="scan">{{ __('receipt.scan') }}</div>
        </td>
    </tr></table>

    <table class="status"><tr>
        <td><div class="cap">{{ __('orders.status_heading') }}</div><div class="val">{{ $order->status->label() }}</div></td>
        <td><div class="cap">{{ __('orders.payment_heading') }}</div><div class="val">{{ $order->payment_status->label() }}</div></td>
    </tr></table>

    @if($order->status === \App\Enums\OrderStatus::Rejected)
        <div class="alert">
            <strong>{{ __('account.rejected_heading') }}</strong>
            @if($order->rejectionReason())
                <div>{{ $d($order->rejectionReason()) }}</div>
            @endif
        </div>
    @endif

    <table class="facts">
        <tr><td class="label">{{ __('orders.order_number') }}</td><td><span dir="ltr">{{ $order->order_number }}</span></td></tr>
        <tr><td class="label">{{ __('order_pdf.placed_on') }}</td><td>{{ $d($order->created_at?->locale($locale)->translatedFormat('j F Y, H:i')) }}</td></tr>
        <tr><td class="label">{{ __('orders.service') }}</td><td>{{ $d($serviceName) }}</td></tr>
        @if($order->customer_name || $order->customer_phone)
            <tr><td class="label">{{ __('receipt.customer') }}</td><td>{{ $d($order->customer_name) }}@if($order->customer_phone) <span dir="ltr" class="muted">&nbsp;·&nbsp;+{{ $order->customer_phone }}</span>@endif</td></tr>
        @endif
        @if($order->receipt_number)
            <tr><td class="label">{{ __('orders.receipt_number') }}</td><td><span dir="ltr">{{ $order->receipt_number }}</span></td></tr>
        @endif
        @if($order->paid_at)
            <tr><td class="label">{{ __('receipt.date') }}</td><td>{{ $d($order->paid_at->locale($locale)->translatedFormat('j F Y, H:i')) }}</td></tr>
        @endif
    </table>

    <h2>{{ __('order_pdf.amounts') }}</h2>
    <table class="lines">
        <tr><th>{{ __('receipt.description') }}</th><th class="amount">{{ __('receipt.amount') }}</th></tr>
        <tr><td>{{ $d($serviceName) }}</td><td class="amount"><span dir="ltr">{{ $money($order->price) }}</span></td></tr>
        @if((float) $order->fee_amount > 0)
            <tr><td>{{ __('orders.service_fee') }}</td><td class="amount"><span dir="ltr">{{ $money($order->fee_amount) }}</span></td></tr>
        @endif
        @if($vat > 0)
            <tr><td>{{ __('orders.vat', ['rate' => $rate]) }}</td><td class="amount"><span dir="ltr">{{ $money($vat) }}</span></td></tr>
        @endif
        <tr class="total"><td>{{ __('orders.total') }}</td><td class="amount"><span dir="ltr">{{ $money($order->total) }}</span></td></tr>
    </table>

    @if($order->refunds->isNotEmpty())
        <h2>{{ __('account.refunds') }}</h2>
        <table class="lines">
            @foreach($order->refunds as $refund)
                <tr><td><span dir="ltr">{{ $refund->created_at->format('Y-m-d') }}</span></td><td class="amount"><span dir="ltr">{{ $money($refund->amount) }}</span></td></tr>
            @endforeach
        </table>
    @endif

    @if(count($answers))
        <h2>{{ __('account.your_request') }}</h2>
        <table class="lines">
            @foreach($answers as $answer)
                <tr><td class="label">{{ $d($answer['label']) }}</td><td>{!! nl2br(e($d($answer['value']))) !!}</td></tr>
            @endforeach
        </table>
    @endif

    @if($order->documents->isNotEmpty())
        <h2>{{ __('account.documents') }}</h2>
        <table class="lines">
            @foreach($order->documents as $document)
                <tr><td>{{ $d($document->title) }}</td><td class="amount muted"><span dir="ltr">{{ $document->humanSize() }}</span></td></tr>
            @endforeach
        </table>
    @endif

    @if($events->isNotEmpty())
        <h2>{{ __('orders.timeline') }}</h2>
        <table class="lines">
            @foreach($events as $event)
                <tr><td>{{ $event->label() }}</td><td class="amount muted"><span dir="ltr">{{ $event->created_at->format('Y-m-d H:i') }}</span></td></tr>
            @endforeach
        </table>
    @endif

    <p class="small muted" style="margin-top: 8mm;">{{ __('order_pdf.printed_at', ['date' => now()->locale($locale)->translatedFormat('j F Y, H:i')]) }}</p>
</body>
</html>
