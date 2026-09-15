<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
>
    <div class="mx-auto max-w-7xl px-6 py-14">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('videos.library.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                {{ __('videos.library.title') }}
            </h1>
            <p class="mt-3 text-warm-900/70">
                {{ __('videos.library.subtitle') }}
            </p>
        </div>

        @if($folders->isEmpty())
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('videos.library.no_results_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('videos.library.no_results_body') }}</p>
            </div>
        @else
            <div class="mt-14 space-y-16">
                @foreach($folders as $folder)
                    <section data-reveal>
                        <h2 class="font-display text-2xl font-semibold text-warm-900">{{ $folder->name }}</h2>

                        <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($folder->videos as $video)
                                <a href="{{ route('video-library.show', $video->slug) }}" data-reveal-item data-tilt-card class="card-warm group overflow-hidden p-0">
                                    <div class="relative aspect-video overflow-hidden">
                                        @if($video->thumbnail_url)
                                            <img data-tilt-image src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="h-full w-full object-cover transition-transform duration-500">
                                        @endif
                                        <div class="absolute inset-0 flex items-center justify-center bg-warm-950/20 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 text-warm-900 shadow-lg">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ml-1 h-6 w-6">
                                                    <path d="M8 5v14l11-7z" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-5">
                                        <h3 class="font-display text-lg font-semibold text-warm-900">{{ $video->title }}</h3>
                                        @if($video->description)
                                            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-warm-900/70">
                                                {{ $video->description }}
                                            </p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.site>
