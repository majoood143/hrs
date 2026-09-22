@php
    $horses = \App\Models\Horse::featured()->with(['type', 'city', 'country'])->latest('id')->take((int) ($data['count'] ?? 6))->get();
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('Featured Horses');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

@if($horses->isNotEmpty())
<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('Meet the horses') }}</p>
        <h2 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ $heading }}
        </h2>
        @if($subheading)
            <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
        @endif
    </div>

    <div data-reveal-group class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($horses as $horse)
            <article data-reveal-item data-tilt-card class="card-warm overflow-hidden">
                <div class="aspect-[4/3] overflow-hidden">
                    <img data-tilt-image src="{{ $horse->cover_photo_url }}" alt="{{ $horse->name }}" class="h-full w-full object-cover transition-transform duration-500">
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-xl font-semibold text-warm-900">{{ $horse->name }}</h3>
                        @if($horse->type)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-semibold text-warm-700">{{ $horse->type->name }}</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-warm-900/60">
                        @if($horse->city)
                            📍 {{ $horse->city->name }}, {{ $horse->country?->name }}
                        @endif
                    </p>
                    @if($horse->public_story)
                        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-warm-900/75">
                            {{ $horse->public_story }}
                        </p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif
