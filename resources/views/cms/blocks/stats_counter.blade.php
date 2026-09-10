@php
    $heading = \App\Support\Localized::value($data, 'heading');
@endphp

@if(!empty($data['stats']))
<section class="bg-warm-900 py-16 text-warm-50">
    <div class="mx-auto max-w-6xl px-6">
        @if($heading)
            <h2 class="text-center font-display text-2xl font-semibold" data-reveal>{{ $heading }}</h2>
        @endif

        <div data-reveal-group class="mt-10 grid gap-8 text-center sm:grid-cols-3">
            @foreach($data['stats'] as $stat)
                <div data-reveal-item>
                    <p class="font-display text-4xl font-bold text-warm-300 sm:text-5xl">
                        <span data-counter="{{ (float) ($stat['number'] ?? 0) }}" data-counter-suffix="{{ $stat['suffix'] ?? '' }}">0{{ $stat['suffix'] ?? '' }}</span>
                    </p>
                    <p class="mt-2 text-sm uppercase tracking-wide text-warm-100/70">{{ \App\Support\Localized::value($stat, 'label') }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
