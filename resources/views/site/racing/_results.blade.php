@include('site.racing._table', ['table' => $detail['table']])

@if($detail['owners'])
    <h3 class="mt-8 font-display text-lg font-semibold text-warm-900">{{ __('racing.owners') }}</h3>
    <ol class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-warm-900/80">
        @foreach($detail['owners'] as $owner)
            <li><span class="text-warm-900/50">{{ $owner['n'] }}.</span> <x-racing.entity type="owner" :entity="$owner" /></li>
        @endforeach
    </ol>
@endif

@if($detail['overweights'])
    <h3 class="mt-6 font-display text-lg font-semibold text-warm-900">{{ __('racing.overweights') }}</h3>
    <p class="mt-2 text-sm text-warm-900/80">{{ $detail['overweights'] }}</p>
@endif
