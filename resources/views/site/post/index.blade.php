<x-layouts.site :seo-title="__('Stories & News') . ' — ' . \App\Models\SiteSetting::siteName()">
    <div class="mx-auto max-w-5xl px-6 py-16">
        <div class="text-center" data-reveal>
            <p class="section-eyebrow">{{ __('From the road') }}</p>
            <h1 class="mt-2 font-display text-4xl font-semibold text-warm-900">
                {{ $heading ?? __('Stories & News') }}
            </h1>
        </div>

        <div data-reveal-group class="mt-12 grid gap-8 sm:grid-cols-2">
            @forelse($posts as $post)
                <a href="{{ url('/blog/' . $post->slug) }}" data-reveal-item class="card-warm overflow-hidden">
                    @if($post->featuredImageUrl())
                        <div class="aspect-[16/9] overflow-hidden">
                            <img src="{{ $post->featuredImageUrl() }}" alt="" class="h-full w-full object-cover">
                        </div>
                    @endif
                    <div class="p-6">
                        @if($post->category)
                            <span class="text-xs font-semibold uppercase tracking-wide text-warm-600">
                                {{ $post->category->getTranslation('name', app()->getLocale()) }}
                            </span>
                        @endif
                        <h2 class="mt-2 font-display text-xl font-semibold text-warm-900">
                            {{ $post->getTranslation('title', app()->getLocale()) }}
                        </h2>
                        @if($excerpt = $post->getTranslation('excerpt', app()->getLocale()))
                            <p class="mt-2 line-clamp-2 text-sm text-warm-900/70">{{ $excerpt }}</p>
                        @endif
                        <p class="mt-4 text-xs text-warm-900/50">{{ $post->published_at?->format('M d, Y') }}</p>
                    </div>
                </a>
            @empty
                <p class="col-span-2 text-center text-warm-900/60">{{ __('No stories published yet.') }}</p>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $posts->links() }}
        </div>
    </div>
</x-layouts.site>
