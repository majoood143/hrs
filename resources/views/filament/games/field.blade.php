@php
    use App\Models\GameAttempt;
    use App\Models\GameRace;
    use App\Services\Games\GameScore;
@endphp
<p class="mb-3 text-sm text-gray-500">
    {{ $race->race_date->translatedFormat('j M Y') }} {{ $race->post_time }} · {{ $race->distance }} m
    @if($race->has_result && $race->winning_time_ms) · {{ __('games.admin.fields.winning_time') }} <span dir="ltr">{{ GameAttempt::formatTime($race->winning_time_ms) }}</span>@endif
</p>
<table class="w-full text-sm">
    <thead>
        <tr class="text-gray-500">
            <th class="py-1 text-start">{{ __('games.admin.fields.gate') }}</th>
            <th class="py-1 text-start">{{ __('games.admin.fields.horse') }}</th>
            <th class="py-1 text-start">{{ __('games.admin.fields.jockey') }}</th>
            <th class="py-1 text-start">{{ __('games.admin.fields.rating') }}</th>
            <th class="py-1 text-start">{{ __('games.admin.fields.multiplier') }}</th>
            @if($race->has_result)<th class="py-1 text-start">{{ __('games.admin.fields.real_position') }}</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($race->runners as $runner)
            <tr class="border-t border-gray-200 dark:border-white/10">
                <td class="py-1.5">{{ $runner['gate'] ?? '—' }}</td>
                <td class="py-1.5 font-medium">{{ $runner['no'] }}. {{ GameRace::text($runner['name']) }}</td>
                <td class="py-1.5">{{ GameRace::text($runner['jockey']) ?? '—' }}</td>
                <td class="py-1.5">{{ $runner['rating'] ?? '—' }}</td>
                <td class="py-1.5">×{{ GameScore::multiplier($race->ratingRank((string) $runner['no']), $race->fieldSize()) }}</td>
                @if($race->has_result)<td class="py-1.5">{{ $runner['result'] ?? '—' }}</td>@endif
            </tr>
        @endforeach
    </tbody>
</table>
