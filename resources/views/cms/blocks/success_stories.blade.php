@php
    $stories = \App\Models\SuccessStory::published()->take((int) ($data['count'] ?? 3))->get();
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('Success Stories');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

@if($stories->isNotEmpty())
<section class="bg-warm-100/50 py-20">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('Happy endings') }}</p>
            <h2 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                {{ $heading }}
            </h2>
            @if($subheading)
                <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
            @endif
        </div>

        <div data-reveal-group class="mt-12 grid gap-8 md:grid-cols-3">
            @foreach($stories as $story)
                <figure data-reveal-item class="card-warm flex h-full flex-col p-6">
                    <div class="text-3xl text-warm-400">"</div>
                    <blockquote class="flex-1 text-sm leading-relaxed text-warm-900/85">
                        {{ $story->quote }}
                    </blockquote>
                    <figcaption class="mt-6 flex items-center gap-3">
                        @if($story->photo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($story->photo) }}" alt="{{ $story->owner_name }}" class="h-11 w-11 rounded-full object-cover">
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-warm-200 font-display text-warm-700">
                                {{ mb_substr($story->owner_name, 0, 1) }}
                            </span>
                        @endif
                        <div>
                            <p class="text-sm font-semibold text-warm-900">{{ $story->owner_name }}</p>
                            @if($story->route)
                                <p class="text-xs text-warm-900/60">{{ $story->route }}</p>
                            @endif
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif
