<x-layouts.site
    :seo-title="$post->getTranslation('meta_title', app()->getLocale()) ?: $post->getTranslation('title', app()->getLocale())"
    :seo-description="$post->getTranslation('meta_description', app()->getLocale()) ?: $post->getTranslation('excerpt', app()->getLocale())"
    :canonical-url="$post->canonical_url"
    :custom-css="$post->custom_css"
>
    <article>
        @if($post->featuredImageUrl())
            <div class="mx-auto max-w-5xl px-6 pt-10">
                <div class="aspect-[21/9] overflow-hidden rounded-3xl">
                    <img src="{{ $post->featuredImageUrl() }}" alt="" class="h-full w-full object-cover">
                </div>
            </div>
        @endif

        @if($post->show_title)
            <div class="mx-auto max-w-3xl px-6 pt-10 text-center">
                @if($post->category)
                    <span class="section-eyebrow">{{ $post->category->getTranslation('name', app()->getLocale()) }}</span>
                @endif
                <h1 class="mt-2 font-display text-4xl font-semibold text-warm-900">
                    {{ $post->getTranslation('title', app()->getLocale()) }}
                </h1>
                <p class="mt-3 text-sm text-warm-900/50">{{ $post->published_at?->format('M d, Y') }}</p>
            </div>
        @endif

        @include('cms.render-blocks', ['blocks' => $post->content ?? []])

        @if($post->tags->isNotEmpty())
            <div class="mx-auto max-w-3xl px-6 pb-16">
                <div class="flex flex-wrap gap-2">
                    @foreach($post->tags as $tag)
                        <a href="{{ url('/blog/tag/' . $tag->slug) }}" class="rounded-full bg-warm-100 px-3 py-1 text-xs font-semibold text-warm-700 hover:bg-warm-200">
                            #{{ $tag->getTranslation('name', app()->getLocale()) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </article>
</x-layouts.site>
