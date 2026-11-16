{{-- The catalogue's race record in one line: runs, wins, places and the handicap rating. --}}
@if($stallion->runs === 0 || ($stallion->runs === null && $stallion->wins === null && $stallion->places === null))
    @if($stallion->runs === 0)
        <p class="{{ $class ?? '' }}">{{ __('stallions.unraced') }}</p>
    @endif
@else
    <dl class="{{ $class ?? '' }} flex flex-wrap gap-x-4 gap-y-1">
        @foreach(['runs', 'wins', 'places', 'rating'] as $field)
            @continue($stallion->{$field} === null)
            <div class="flex items-baseline gap-1">
                <dd class="font-display font-semibold text-warm-900">{{ $stallion->{$field} }}</dd>
                <dt class="text-warm-900/60">{{ __('stallions.'.$field) }}</dt>
            </div>
        @endforeach
    </dl>
@endif
