@php
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $m = fn (int $baisa) => \App\Support\Money::formatPdfHtml($baisa, $currencyIcon ?? null, $currency);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('stable_statement.title') }}</title>
    <style>
        body { font-family: cairo; font-size: 8.5pt; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 16pt; color: #000000; margin: 0 0 1mm 0; }
        h2 { font-size: 10.5pt; color: #000000; margin: 5mm 0 2mm 0; padding-bottom: 1mm; border-bottom: 0.3mm solid #999999; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 3mm; }
        .brand td { padding: 0 0 2mm 0; vertical-align: middle; }
        .site { font-size: 14pt; font-weight: bold; color: #000000; }
        table.facts td { padding: 0.4mm 4mm 0.4mm 0; vertical-align: top; text-align: {{ $align }}; }
        table.facts td.label { color: #666666; }
        table.grid { border-collapse: collapse; width: 100%; font-size: 8pt; }
        table.grid th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 1.2mm 1.5mm; border-bottom: 0.4mm solid #888888; font-size: 7.5pt; }
        table.grid td { padding: 1.1mm 1.5mm; border-bottom: 0.2mm solid #dddddd; vertical-align: top; text-align: {{ $align }}; }
        table.grid .num { text-align: {{ $opposite }}; }
        table.grid tr.strong td { font-weight: bold; color: #000000; background-color: #f2f2f2; border-top: 0.4mm solid #888888; }
        .balance { margin-top: 3mm; padding: 2.5mm 3mm; border: 0.4mm solid #000000; font-size: 10pt; font-weight: bold; color: #000000; }
        .note { margin-top: 5mm; padding: 2.5mm; border: 0.3mm dashed #aaaaaa; font-size: 7.5pt; color: #555555; }
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
        <h1>{{ __('stable_statement.title') }}</h1>
    </td></tr></table>

    <table class="facts">
        <tr><td class="label">{{ __('stable_statement.fields.stable') }}</td><td>{{ $d($stable->name) }}</td></tr>
        <tr><td class="label">{{ __('statement.period') }}</td><td><span dir="ltr">{{ $statement->from->toDateString() }} &rarr; {{ $statement->to->toDateString() }}</span></td></tr>
        @if($stable->formatted_commission)
            <tr><td class="label">{{ __('stable_panel.fields.commission') }}</td><td><span dir="ltr">{{ $stable->formatted_commission }}</span></td></tr>
        @endif
        <tr><td class="label">{{ __('statement.generated') }}</td><td><span dir="ltr">{{ now()->format('Y-m-d H:i') }}</span></td></tr>
    </table>

    <h2>{{ __('statement.summary') }}</h2>
    <table class="grid">
        @foreach($summary as $line)
            <tr @class(['strong' => $line[2] ?? false])>
                <td>{{ $line[0] }}</td>
                <td class="num"><span dir="ltr">{{ $m($line[1]) }}</span></td>
            </tr>
        @endforeach
    </table>
    <div class="balance">{{ $balanceSentence }}</div>

    <h2>{{ __('stable_statement.sections.bookings') }}</h2>
    @if($rows->isEmpty())
        <p class="empty">{{ __('stable_statement.empty') }}</p>
    @else
        <table class="grid">
            <tr>
                <th>{{ __('statement.date') }}</th>
                <th>{{ __('stable_statement.fields.booking') }}</th>
                <th>{{ __('stable_statement.fields.session') }}</th>
                <th class="num">{{ __('stable_statement.fields.riders') }}</th>
                <th>{{ __('stable_statement.fields.paid_how') }}</th>
                <th class="num">{{ __('stable_statement.fields.collected') }}</th>
                <th class="num">{{ __('stable_statement.fields.refunded') }}</th>
                <th class="num">{{ __('stable_statement.fields.stable_share') }}</th>
                <th class="num">{{ __('stable_statement.fields.fee') }}</th>
                <th class="num">{{ __('stable_statement.fields.commission') }}</th>
                <th class="num">{{ __('stable_statement.fields.net') }}</th>
            </tr>
            @foreach($rows as $row)
                <tr>
                    <td><span dir="ltr">{{ $row->date->format('Y-m-d') }}</span></td>
                    <td><span dir="ltr">{{ $row->bookingReference ?? $row->orderNumber }}</span></td>
                    <td>{{ $d($row->service) }}@if($row->sessionDate) <span dir="ltr">({{ $row->sessionDate }})</span>@endif</td>
                    <td class="num">{{ $row->riders }}</td>
                    <td>{{ __('stable_statement.kinds.'.$row->kind) }}</td>
                    <td class="num"><span dir="ltr">{{ $m($row->collected()) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->refunded) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->stableShare()) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->fee + $row->vatOnFee) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->commission + $row->vatOnCommission) }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($row->net()) }}</span></td>
                </tr>
            @endforeach
            <tr class="strong">
                <td colspan="3">{{ __('stable_statement.fields.total') }}</td>
                <td class="num">{{ $totals['riders'] }}</td>
                <td></td>
                <td class="num"><span dir="ltr">{{ $m($totals['collected']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $m($totals['refunded']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $m($totals['stable_share']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $m($totals['fee']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $m($totals['commission'] + $totals['vat_on_commission']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $m($totals['net']) }}</span></td>
            </tr>
        </table>
    @endif

    <h2>{{ __('stable_statement.sections.settlements') }}</h2>
    @if($settlements->isEmpty())
        <p class="empty">{{ __('stable_statement.no_settlements') }}</p>
    @else
        <table class="grid">
            <tr>
                <th>{{ __('stable_statement.fields.paid_on') }}</th>
                <th>{{ __('stable_statement.fields.direction') }}</th>
                <th>{{ __('stable_statement.fields.method') }}</th>
                <th>{{ __('stable_statement.fields.reference') }}</th>
                <th class="num">{{ __('stable_statement.fields.amount') }}</th>
            </tr>
            @foreach($settlements as $settlement)
                <tr>
                    <td><span dir="ltr">{{ $settlement->paid_on->toDateString() }}</span></td>
                    <td>{{ __('stable_statement.directions.'.$settlement->direction) }}</td>
                    <td>{{ __('stable_statement.methods.'.$settlement->method) }}</td>
                    <td><span dir="ltr">{{ $settlement->reference }}</span></td>
                    <td class="num"><span dir="ltr">{{ $m($settlement->amountBaisa()) }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="note">{{ __('stable_statement.note') }}</div>
</body>
</html>
