{{-- Answer rows with a gray bar (a one-cell table: mPDF does not paint a sized div inside a cell). --}}
@php
    $max = max(1, ...array_map(fn (array $row) => (int) $row['count'], $rows ?: [['count' => 1]]));
@endphp
<table class="grid">
    <thead>
        <tr>
            <th>{{ __('form_charts.answer') }}</th>
            <th class="num">{{ __('form_charts.count') }}</th>
            <th class="num">{{ __('form_charts.share') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            @php
                $share = ($shares ?? false) ? (float) $row['share'] : round($row['count'] / $total * 100, 1);
                $width = max(0.5, round($row['count'] / $max * 55, 1));
            @endphp
            <tr>
                <td>{{ $d($row['label']) }}</td>
                <td class="num"><span dir="ltr">{{ number_format($row['count']) }}</span></td>
                <td class="num"><span dir="ltr">{{ $pct($share) }}</span></td>
                <td class="bar">
                    @if($row['count'] > 0)
                        <table class="meter" style="width: {{ $width }}mm;"><tr><td>&nbsp;</td></tr></table>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
