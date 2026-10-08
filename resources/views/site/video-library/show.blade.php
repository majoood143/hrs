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

        @if($descriptionHtml = $video->descriptionHtml())
            <div class="mt-8 space-y-3 text-sm leading-relaxed text-warm-900/80 [&_a]:text-warm-700 [&_a]:underline [&_a:hover]:text-warm-900 [&_blockquote]:border-s-4 [&_blockquote]:border-warm-200 [&_blockquote]:ps-4 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-warm-900 [&_h3]:font-display [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-warm-900 [&_li]:ms-5 [&_ol]:list-decimal [&_ul]:list-disc">
                {!! $descriptionHtml !!}
            </div>
        @endif

        <div class="mt-8">
            <x-share-buttons :url="url()->current()" :title="$video->title" />
        </div>
    </div>
</x-layouts.site>
