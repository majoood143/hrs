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
