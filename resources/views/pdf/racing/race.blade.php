@extends('pdf.racing.layout')

@section('content')
    <p class="eyebrow">{{ __('racing.pages.' . $page . '.title') }}@if($meeting['heading'] ?? null) &nbsp;·&nbsp; {{ $d($meeting['heading']) }}@endif</p>
    <h1>{{ $d($selected['title']) }}</h1>
    <p class="muted">{{ __('racing.race_n', ['n' => $selected['number'] ?? '']) }}@foreach($selected['info'] as $item) &nbsp;·&nbsp; {{ $d($item) }}@endforeach</p>

    <table width="100%"><tr>
        <td style="vertical-align: top;">
            @if($selected['facts'])
                <table class="facts">
                    @foreach($selected['facts'] as $fact)
                        <tr><td class="label">{{ $d($fact['label']) }}</td><td>{{ $d($fact['value']) }}</td></tr>
                    @endforeach
                </table>
            @endif

            @if($selected['conditions'])
                <p style="margin-top: 2mm;"><span class="muted">{{ $d($selected['conditions']['label']) }}</span><br>@foreach(preg_split('/\R/u', $selected['conditions']['value']) as $line){{ $d($line) }}<br>@endforeach</p>
            @endif

            @if($selected['prizes'])
                <table style="margin-top: 2mm; font-size: 8pt;"><tr>
                    <td class="muted" style="padding-right: 3mm;">{{ __('racing.prize_money') }}:</td>
                    @foreach($selected['prizes'] as $prize)
                        <td style="padding-right: 4mm;"><span dir="ltr">{{ $prize['place'] }} {{ $prize['amount'] }}</span></td>
                    @endforeach
                </tr></table>
            @endif
        </td>

        @if($selected['running_time'])
            <td width="38mm" style="vertical-align: top;">
                <table class="box"><tr><td>
                    <div class="eyebrow">{{ $d($selected['running_time']['label']) }}</div>
                    <div class="value" dir="ltr">{{ $selected['running_time']['value'] }}</div>
                </td></tr></table>
            </td>
        @endif
    </tr></table>

    <h2>{{ __('racing.pages.' . $page . '.title') }}</h2>

    @if(in_array($page, ['results', 'entries'], true))
        @include('pdf.racing._table', ['table' => $detail['table']])
    @endif

    @if($page === 'results')
        @if($detail['owners'])
            <h3>{{ __('racing.owners') }}</h3>
            <p class="small">
                @foreach($detail['owners'] as $owner)
                    {{ $owner['n'] }}. @include('pdf.racing._entity', ['type' => 'owner', 'entity' => $owner])@unless($loop->last) &nbsp;·&nbsp; @endunless
                @endforeach
            </p>
        @endif

        @if($detail['overweights'])
            <h3>{{ __('racing.overweights') }}</h3>
            <p class="small">{{ $d($detail['overweights']) }}</p>
        @endif
    @endif

    @if($page === 'card')
        <table class="grid" repeat_header="1">
            <thead><tr>
                <th>{{ __('racing.card_labels.no') }}</th>
                <th>{{ __('racing.card_labels.gate') }}</th>
                <th>{{ __('racing.handicap.columns.horse') }}</th>
                <th>{{ __('racing.card_labels.record') }}</th>
                <th>{{ __('racing.card_labels.owner') }}</th>
                <th>{{ __('racing.card_labels.trainer') }}</th>
                <th>{{ __('racing.card_labels.jockey') }}</th>
                <th>{{ __('racing.card_labels.weight') }}</th>
            </tr></thead>
            <tbody>
                @foreach($detail['runners'] as $runner)
                    <tr>
                        <td>{{ $runner['no'] }}</td>
                        <td>{{ $runner['gate'] }}</td>
                        <td>@include('pdf.racing._entity', ['type' => 'horse', 'entity' => $runner['horse']])<br><span class="muted small">@foreach($runner['meta'] as $part){{ $d($part) }}@unless($loop->last) · @endunless @endforeach</span></td>
                        <td><span dir="ltr">{{ $runner['record'] }}</span></td>
                        <td>@include('pdf.racing._entity', ['type' => 'owner', 'entity' => $runner['owner']])</td>
                        <td>@include('pdf.racing._entity', ['type' => 'trainer', 'entity' => $runner['trainer']])</td>
                        <td>@include('pdf.racing._entity', ['type' => 'jockey', 'entity' => $runner['jockey']])</td>
                        <td><span dir="ltr">{{ $runner['weight'] }}</span>@if($runner['weight_note'])<br><span class="muted small" dir="ltr">{{ $runner['weight_note'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($page === 'form-guide')
        @foreach($detail['runners'] as $runner)
            <h3>@if($runner['no']){{ $runner['no'] }}. @endif@include('pdf.racing._entity', ['type' => 'horse', 'entity' => $runner['horse']])</h3>
            <p class="small muted">
                @php
                    $line = array_filter([
                        ...$runner['meta'],
                        $runner['record'],
                        $runner['owner'] ? __('racing.card_labels.owner') . ': ' . $runner['owner']['name'] : null,
                        $runner['trainer'] ? __('racing.card_labels.trainer') . ': ' . $runner['trainer']['name'] : null,
                        $runner['jockey'] ? __('racing.card_labels.jockey') . ': ' . $runner['jockey']['name'] : null,
                    ]);
                @endphp
                @foreach($line as $part){{ $d($part) }}@unless($loop->last) &nbsp;·&nbsp; @endunless @endforeach
            </p>

            @if($runner['runs']['rows'])
                @include('pdf.racing._table', ['table' => $runner['runs']])
            @else
                <p class="empty">{{ __('racing.no_previous_runs') }}</p>
            @endif
        @endforeach
    @endif
@endsection
