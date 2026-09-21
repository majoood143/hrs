@extends('pdf.racing.layout')

@section('content')
    <table width="100%"><tr>
        @if($image)
            <td width="26mm" style="vertical-align: top;"><img src="{{ $image }}" width="22mm" height="22mm" alt=""></td>
        @endif

        <td style="vertical-align: top;">
            <p class="eyebrow">{{ __('racing.profile_types.' . $entity) }}</p>
            <h1>{{ $d($profile['title']) }}</h1>

            @if($profile['meta'] || $profile['days_since_run'])
                <p class="muted">@foreach(array_filter([...$profile['meta'], $profile['days_since_run']]) as $part){{ $d($part) }}@unless($loop->last) &nbsp;·&nbsp; @endunless @endforeach</p>
            @endif

            @if($profile['pedigree'])
                <p class="muted small">{{ $d($profile['pedigree']) }}</p>
            @endif

            @if($profile['facts'])
                <table class="facts" style="margin-top: 2mm;">
                    @foreach($profile['facts'] as $fact)
                        <tr>
                            <td class="label">{{ $d($fact['label']) }}</td>
                            <td>
                                @if($href = \App\Support\RacingLinks::href($fact['link'] ?? null))<a href="{{ $href }}">{{ $d($fact['value']) }}</a>@else{{ $d($fact['value']) }}@endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>

        @if($profile['rating'])
            <td width="38mm" style="vertical-align: top;">
                <table class="box"><tr><td>
                    <div class="value">{{ $profile['rating']['value'] }}</div>
                    <div class="eyebrow">{{ __('racing.rating') }}</div>
                </td></tr></table>
            </td>
        @endif
    </tr></table>

    @foreach($profile['tabs'] as $tab)
        <h2>{{ $d($tab['label']) }}</h2>

        @forelse($tab['tables'] as $table)
            @include('pdf.racing._table', ['table' => $table])
        @empty
            <p class="empty">{{ $d($tab['message'] ?: __('racing.no_data')) }}</p>
        @endforelse
    @endforeach
@endsection
