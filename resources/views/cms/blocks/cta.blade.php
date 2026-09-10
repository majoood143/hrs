@php
    $isDark = ($data['style'] ?? 'warm') === 'dark';
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('Ready to give your horse a caring journey?');
    $subheading = \App\Support\Localized::value($data, 'subheading');
    $buttonText = \App\Support\Localized::value($data, 'button_text');
@endphp

<section class="px-6 py-20">
    <div data-reveal class="mx-auto max-w-4xl overflow-hidden rounded-[2.5rem] px-8 py-14 text-center {{ $isDark ? 'bg-warm-950 text-warm-50' : 'bg-gradient-to-br from-warm-500 to-clay-600 text-white' }}">
        <h2 class="font-display text-3xl font-semibold sm:text-4xl">
            {{ $heading }}
        </h2>
        @if($subheading)
            <p class="mx-auto mt-4 max-w-xl text-balance opacity-90">{{ $subheading }}</p>
        @endif
        @if($buttonText)
            <a href="{{ $data['button_url'] ?? url('/transportation') }}" class="mt-8 inline-flex items-center justify-center gap-2 rounded-full bg-white px-8 py-3 font-semibold text-warm-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                {{ $buttonText }}
            </a>
        @endif
    </div>
</section>
