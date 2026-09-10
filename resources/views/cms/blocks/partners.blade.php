@php
    $partners = \App\Models\Partner::active()->get();
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('Trusted Partners');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

@if($partners->isNotEmpty())
<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('Who we work with') }}</p>
        <h2 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ $heading }}
        </h2>
        @if($subheading)
            <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
        @endif
    </div>

    <div data-reveal-group class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($partners as $partner)
            <a data-reveal-item href="{{ $partner->website_url ?: '#' }}" @if($partner->website_url) target="_blank" rel="noopener" @endif
               class="card-warm flex flex-col items-center gap-4 p-6 text-center">
                @if($partner->logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($partner->logo) }}" alt="{{ $partner->name }}" class="h-14 w-14 rounded-full object-cover">
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-warm-100 text-2xl">🤝</span>
                @endif
                <div>
                    <p class="font-semibold text-warm-900">{{ $partner->name }}</p>
                    <p class="text-xs uppercase tracking-wide text-warm-600">{{ __("partner.types.{$partner->type}") }}</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif
