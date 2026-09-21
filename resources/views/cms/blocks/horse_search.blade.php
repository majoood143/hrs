@php
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('racing.default_heading');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

<section class="mx-auto max-w-5xl px-6 py-12" data-reveal>
    <div class="card-warm p-6 sm:p-8">
        <div class="mb-6 text-center">
            <h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">{{ $heading }}</h2>
            @if($subheading)
                <p class="mt-2 text-warm-900/70">{{ $subheading }}</p>
            @endif
        </div>

        @include('site.racing._form', ['type' => $data['default_type'] ?? 1, 'q' => '', 'idSuffix' => 'block'])
    </div>
</section>
