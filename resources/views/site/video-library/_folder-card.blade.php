@php($count = $folder->activeVideosCount())

<a href="{{ route('video-library.folder', $folder->slug) }}" data-reveal-item data-tilt-card class="card-warm group overflow-hidden p-0">
    <div class="relative flex aspect-video items-center justify-center overflow-hidden bg-warm-100">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-16 w-16 text-warm-400 transition-transform duration-500 group-hover:scale-105">
            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
        </svg>
    </div>
    <div class="p-5">
        <h3 class="font-display text-lg font-semibold text-warm-900">{{ $folder->name }}</h3>
        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-warm-600">
            {{ trans_choice('videos.library.videos_count', $count, ['count' => $count]) }}
        </p>
    </div>
</a>
