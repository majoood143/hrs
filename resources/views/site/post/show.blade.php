<x-layouts.site
    :seo-title="$post->getTranslation('meta_title', app()->getLocale()) ?: $post->getTranslation('title', app()->getLocale())"
    :seo-description="$post->getTranslation('meta_description', app()->getLocale()) ?: $post->getTranslation('excerpt', app()->getLocale())"
    :canonical-url="$post->canonical_url"
    :custom-css="$post->custom_css"
    :custom-head-scripts="$post->custom_head_scripts"
    :custom-body-scripts="$post->custom_body_scripts"
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
                        <a href="{{ url('/blog/tag/' . $tag->slug) }}" class="badge-accent">
                            #{{ $tag->getTranslation('name', app()->getLocale()) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </article>
</x-layouts.site>
