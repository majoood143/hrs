@php
    $heading = \App\Support\Localized::value($data, 'heading');
    $subheading = \App\Support\Localized::value($data, 'subheading');
    $boxed = ($data['container'] ?? 'boxed') !== 'full';
    $slug = $data['form_slug'] ?? null;
@endphp

@if($slug)
    <section class="{{ $boxed ? 'mx-auto max-w-3xl px-6' : 'px-6' }} py-12" data-reveal>
        @if($heading)
            <h2 class="text-center font-display text-3xl font-semibold sm:text-4xl">{{ $heading }}</h2>
        @endif
        @if($subheading)
            <p class="mx-auto mt-3 max-w-xl text-balance text-center opacity-80">{{ $subheading }}</p>
        @endif
        <div class="mt-8">
            <x-form-builder::form :form="$slug" />
        </div>
    </section>
@endif
