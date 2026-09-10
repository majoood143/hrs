@php
    $currentLocale = app()->getLocale();
    $languages = config('languages.available', []);
@endphp

@if(count($languages) > 1)
    <div {{ $attributes->merge(['class' => 'relative inline-flex items-center gap-1 rounded-full border border-warm-200 bg-warm-50/80 p-1 text-xs font-semibold']) }}>
        @foreach($languages as $code => $meta)
            <a
                href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                hreflang="{{ $code }}"
                @if($code === $currentLocale) aria-current="true" @endif
                class="rounded-full px-3 py-1.5 transition {{ $code === $currentLocale ? 'bg-warm-800 text-white' : 'text-warm-800 hover:bg-warm-200' }}"
            >
                {{ $meta['flag'] ?? '' }} {{ $meta['native'] ?? $meta['name'] ?? strtoupper($code) }}
            </a>
        @endforeach
    </div>
@endif
