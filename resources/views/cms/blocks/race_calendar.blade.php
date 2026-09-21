@php
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('racing.calendar.title');
    $subheading = \App\Support\Localized::value($data, 'subheading') ?: __('racing.calendar.subtitle');
@endphp

<section class="mx-auto max-w-6xl px-6 py-12" data-reveal>
    <div class="mb-8 text-center">
        <h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">{{ $heading }}</h2>
        <p class="mt-2 text-warm-900/70">{{ $subheading }}</p>
    </div>

    <x-racing.calendar />
</section>
