@props(['active' => null, 'carry' => null])

@php
    // the race-day pages keep the race you are looking at, like the source's own buttons do
    $items = [
        'search' => route('racing.search'),
        'results' => route('racing.meeting', array_filter(['page' => 'results', 'race' => $carry])),
        'entries' => route('racing.meeting', array_filter(['page' => 'entries', 'race' => $carry])),
        'card' => route('racing.meeting', array_filter(['page' => 'card', 'race' => $carry])),
        'form-guide' => route('racing.meeting', array_filter(['page' => 'form-guide', 'race' => $carry])),
        'handicap' => route('racing.handicap'),
        'calendar' => route('racing.calendar'),
    ];
@endphp

<nav aria-label="{{ __('racing.eyebrow') }}" class="-mx-6 overflow-x-auto px-6 print:hidden">
    <ul class="flex min-w-max gap-2 sm:min-w-0 sm:flex-wrap sm:justify-center">
        @foreach($items as $key => $url)
            <li>
                <a href="{{ $url }}" @if($active === $key) aria-current="page" @endif
                   class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition
                          {{ $active === $key ? 'border-warm-600 bg-warm-600 text-white' : 'border-warm-200 bg-white text-warm-800 hover:border-warm-400 hover:text-warm-900' }}">
                    {{ __('racing.nav.' . $key) }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
