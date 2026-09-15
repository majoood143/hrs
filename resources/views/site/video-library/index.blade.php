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
            <div data-reveal-group class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($folders as $folder)
                    @include('site.video-library._folder-card', ['folder' => $folder])
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.site>
