@php
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $m = fn (int $baisa) => \App\Support\Money::formatPdfHtml($baisa, $currencyIcon ?? null, $currency);
    $pct = fn (float $s): string => rtrim(rtrim(number_format($s, 1, '.', ''), '0'), '.').'%';
    $num = fn (int|float $n): string => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin_form_insights.export.title') }}</title>
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
        table.grid td { padding: 1.3mm 1.8mm; border-bottom: 0.2mm solid #dddddd; vertical-align: middle; text-align: {{ $align }}; }
        table.grid .num { text-align: {{ $opposite }}; width: 16mm; }
        table.grid td.bar { width: 55mm; }
        table.grid tr.strong td { font-weight: bold; color: #000000; background-color: #f2f2f2; }
        table.meter { border-collapse: collapse; }
        table.meter td { height: 2.6mm; padding: 0; background-color: #777777; border: none; }
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
        <h1>{{ __('admin_form_insights.export.title') }}</h1>
        <div class="muted">{{ $d($form) }}</div>
    </td></tr></table>

    <table class="facts">
        <tr><td class="label">{{ __('admin_form_insights.export.period') }}</td><td><span dir="ltr">{{ $period }}</span></td></tr>
        @if($status)
            <tr><td class="label">{{ __('admin_form_insights.filters.order_status') }}</td><td>{{ $status }}</td></tr>
        @endif
        <tr><td class="label">{{ __('admin_form_insights.cards.submissions') }}</td><td>{{ number_format($total) }}@if($previous !== null) <span class="muted">({{ __('admin_form_insights.export.previous_period') }}: {{ number_format($previous) }})</span>@endif</td></tr>
        <tr><td class="label">{{ __('admin_form_insights.cards.unread') }}</td><td>{{ number_format($unread) }}</td></tr>
        <tr><td class="label">{{ __('admin_form_insights.cards.last_submission') }}</td><td><span dir="ltr">{{ $last ?? '—' }}</span></td></tr>
        <tr><td class="label">{{ __('statement.generated') }}</td><td><span dir="ltr">{{ now()->format('Y-m-d H:i') }}</span></td></tr>
    </table>

    @if($money)
        <h2>{{ __('admin_form_insights.sections.money') }}</h2>
        <table class="grid">
            @foreach($moneyLines as [$label, $baisa])
                <tr @class(['strong' => in_array($label, [__('statement.due_to_us'), __('statement.client_keeps')], true)])>
                    <td>{{ $label }}</td>
                    <td class="num" style="width: 40mm;"><span dir="ltr">{{ $m($baisa) }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($orders)
        <h2>{{ __('admin_form_insights.sections.orders_by_status') }}</h2>
        @include('pdf.form-insights-rows', ['rows' => $orders, 'total' => max(1, array_sum(array_column($orders, 'count')))])
    @endif

    <h2>{{ __('admin_form_insights.sections.over_time') }}</h2>
    @if($timeline === [])
        <div class="empty">{{ __('admin_form_insights.empty') }}</div>
    @else
        @include('pdf.form-insights-rows', ['rows' => $timeline, 'total' => max(1, $total)])
    @endif

    @foreach($fields as $field)
        <h2>{{ $d($field['label']) }}</h2>
        <p class="muted">{{ __('admin_form_insights.field.answered', ['answered' => number_format($field['answered']), 'total' => number_format($field['total']), 'type' => __('admin_form_insights.kinds.'.$field['kind'])]) }}</p>

        @if(! empty($field['stats']))
            <table class="facts">
                <tr>
                    @foreach(['min', 'mean', 'median', 'max'] as $stat)
                        <td class="label">{{ __('admin_form_insights.stats.'.$stat) }}</td><td><span dir="ltr">{{ $num($field['stats'][$stat]) }}</span></td>
                    @endforeach
                </tr>
            </table>
        @endif

        @if($field['answered'] === 0)
            <div class="empty">{{ __('admin_form_insights.field.no_answers') }}</div>
        @else
            @include('pdf.form-insights-rows', ['rows' => $field['rows'], 'total' => $field['answered'], 'shares' => true])
        @endif
    @endforeach
</body>
</html>
