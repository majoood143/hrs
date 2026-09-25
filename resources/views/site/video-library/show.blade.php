<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('video-library.folder', $video->folder->slug) }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ $video->folder->name }}
        </a>

        <div class="mt-6">
            <p class="section-eyebrow">{{ $video->folder->name }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $video->title }}</h1>
            <x-listing-meta class="mt-3" :views="$video->views_count" />
        </div>

        @if($video->embed_url)
            <div class="mt-8 aspect-video overflow-hidden rounded-3xl shadow-lg shadow-warm-900/10">
                <iframe
                    class="h-full w-full"
                    src="{{ $video->embed_url }}"
                    title="{{ $video->title }}"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                ></iframe>
            </div>
        @endif

        @if($video->description)
            <div class="mt-8">
                <p class="whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $video->description }}</p>
            </div>
        @endif

        <div class="mt-8">
            <x-share-buttons :url="url()->current()" :title="$video->title" />
        </div>
    </div>
</x-layouts.site>
