@php
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $m = fn (int $baisa) => \App\Support\Money::formatPdfHtml($baisa, $currencyIcon ?? null, $currency);
    $strong = [__('statement.due_to_us'), __('statement.client_keeps')];
    $commissionLabel = $totals['vat_on_commission'] > 0 ? __('statement.commission_and_vat') : __('statement.commission');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: cairo; font-size: 9pt; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 18pt; color: #000000; margin: 0 0 1mm 0; }
        h2 { font-size: 11pt; color: #000000; margin: 6mm 0 2mm 0; padding-bottom: 1mm; border-bottom: 0.3mm solid #999999; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 4mm; }
        .brand td { padding: 0 0 2mm 0; vertical-align: middle; }
        .site { font-size: 14pt; font-weight: bold; color: #000000; }
        .muted { color: #666666; }
        table.facts td { padding: 0.5mm 4mm 0.5mm 0; vertical-align: top; text-align: {{ $align }}; }
        table.facts td.label { color: #666666; }
        table.grid { border-collapse: collapse; width: 100%; font-size: 8.5pt; }
        table.grid th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 1.4mm 1.8mm; border-bottom: 0.4mm solid #888888; font-size: 8pt; }
        table.grid td { padding: 1.3mm 1.8mm; border-bottom: 0.2mm solid #dddddd; vertical-align: top; text-align: {{ $align }}; }
        table.grid .num { text-align: {{ $opposite }}; }
        table.grid tr.strong td { font-weight: bold; color: #000000; background-color: #f2f2f2; border-top: 0.4mm solid #888888; }
        .note { margin-top: 6mm; padding: 3mm; border: 0.3mm dashed #aaaaaa; font-size: 8pt; color: #555555; }
        .empty { padding: 3mm; text-align: center; color: #666666; border: 0.3mm dashed #aaaaaa; }
    </style>
</head>
<body>
    <table class="brand"><tr><td>
        @if($logo)
            <img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}mm" height="{{ $logo['height'] }}mm" alt="{{ $siteName }}">
        @else
            <div class="site">{{ $siteName }}</div>
        @endif
        <h1>{{ __('statement.title') }}</h1>
    </td></tr></table>

    <table class="facts">
        <tr><td class="label">{{ __('statement.period') }}</td><td><span dir="ltr">{{ $statement->from->toDateString() }} &rarr; {{ $statement->to->toDateString() }}</span></td></tr>
        @if($serviceName)
            <tr><td class="label">{{ __('statement.service') }}</td><td>{{ $d($serviceName) }}</td></tr>
        @endif
        @if($gatewayName)
            <tr><td class="label">{{ __('statement.gateway') }}</td><td>{{ $gatewayName }}</td></tr>
        @endif
        <tr><td class="label">{{ __('statement.generated') }}</td><td><span dir="ltr">{{ now()->format('Y-m-d H:i') }}</span></td></tr>
        @if($vatNumber !== '')
            <tr><td class="label">{{ __('statement.vat_number') }}</td><td><span dir="ltr">{{ $vatNumber }}</span></td></tr>
        @endif
    </table>

    <h2>{{ __('statement.summary') }}</h2>
    <table class="grid">
        @foreach($lines as [$label, $baisa])
            <tr @class(['strong' => in_array($label, $strong, true)])>
                <td>{{ $label }}</td>
                <td class="num"><span dir="ltr">{{ $baisa === null ? $totals['orders'] : $m($baisa) }}</span></td>
            </tr>
        @endforeach
    </table>

    @if($rows->isEmpty())
        <p class="empty" style="margin-top: 5mm;">{{ __('statement.empty') }}</p>
    @else
        <h2>{{ __('statement.by_service') }}</h2>
        <table class="grid">
            <tr>
                <th>{{ __('statement.service') }}</th><th class="num">{{ __('statement.orders') }}</th><th class="num">{{ __('statement.collected') }}</th>
                <th class="num">{{ __('statement.fee_and_vat') }}</th><th class="num">{{ $commissionLabel }}</th><th class="num">{{ __('statement.due_to_us') }}</th>
            </tr>
            @foreach($services as $group)
                @php($g = $group['totals'])
                <tr>
                    <td>{{ $d($group['label']) }}</td><td class="num">{{ $g['orders'] }}</td><td class="num"><span dir="ltr">{{ $m($g['collected']) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($g['fee'] + $g['vat_on_fee']) }}</span></td><td class="num"><span dir="ltr">{{ $m(\App\Services\Reports\IncomeStatement::commissionWithVat($g)) }}</span></td><td class="num"><span dir="ltr">{{ $m($g['due_to_us']) }}</span></td>
                </tr>
            @endforeach
        </table>

        <h2>{{ __('statement.by_day') }}</h2>
        <table class="grid">
            <tr>
                <th>{{ __('statement.date') }}</th><th class="num">{{ __('statement.orders') }}</th><th class="num">{{ __('statement.collected') }}</th>
                <th class="num">{{ __('statement.fee_and_vat') }}</th><th class="num">{{ $commissionLabel }}</th><th class="num">{{ __('statement.due_to_us') }}</th>
            </tr>
            @foreach($days as $date => $g)
                <tr>
                    <td><span dir="ltr">{{ $date }}</span></td><td class="num">{{ $g['orders'] }}</td><td class="num"><span dir="ltr">{{ $m($g['collected']) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($g['fee'] + $g['vat_on_fee']) }}</span></td><td class="num"><span dir="ltr">{{ $m(\App\Services\Reports\IncomeStatement::commissionWithVat($g)) }}</span></td><td class="num"><span dir="ltr">{{ $m($g['due_to_us']) }}</span></td>
                </tr>
            @endforeach
        </table>

        <h2>{{ __('statement.orders_list') }}</h2>
        <table class="grid">
            <tr>
                <th>{{ __('statement.date') }}</th><th>{{ __('statement.order') }}</th><th class="num">{{ __('statement.collected') }}</th>
                <th class="num">{{ __('statement.fee_and_vat') }}</th><th class="num">{{ $commissionLabel }}</th><th class="num">{{ __('statement.due_to_us') }}</th>
            </tr>
            @foreach($rows as $row)
                <tr>
                    <td><span dir="ltr">{{ $row->paidAt->format('Y-m-d') }}</span></td><td><span dir="ltr">{{ $row->orderNumber }}</span></td><td class="num"><span dir="ltr">{{ $m($row->total) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->fee + $row->vatOnFee) }}</span></td><td class="num"><span dir="ltr">{{ $m($row->commission + $row->vatOnCommission) }}</span></td><td class="num"><span dir="ltr">{{ $m($row->dueToUs()) }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="note">{{ __('statement.note') }}</div>
</body>
</html>
