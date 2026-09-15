<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription ?? null"
    :seo-image="$seoImage ?? null"
>
    <div class="mx-auto max-w-7xl px-6 py-14">
        <a href="{{ $folder->parent ? route('video-library.folder', $folder->parent->slug) : route('video-library.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ $folder->parent ? $folder->parent->name : __('videos.back_to_library') }}
        </a>

        <div class="mt-6 mx-auto max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('videos.library.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                {{ $folder->name }}
            </h1>
        </div>

        @if($children->isEmpty() && $videos->isEmpty())
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('videos.library.no_folder_results_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('videos.library.no_folder_results_body') }}</p>
            </div>
        @else
            @if($children->isNotEmpty())
                <div data-reveal-group class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($children as $child)
                        @include('site.video-library._folder-card', ['folder' => $child])
                    @endforeach
                </div>
            @endif

            @if($videos->isNotEmpty())
                <div data-reveal-group class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($videos as $video)
                        @include('site.video-library._video-card', ['video' => $video])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-layouts.site>
