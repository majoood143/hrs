@php
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('videos.library.title');
    $subheading = \App\Support\Localized::value($data, 'subheading');
    $count = (int) ($data['count'] ?? 6);

    $query = \App\Models\Video::query()->active();

    if (! empty($data['folder_id'])) {
        $query->where('folder_id', $data['folder_id']);
    }

    $videos = $query->orderBy('order')->latest('id')->take($count)->get();
@endphp

@if($videos->isNotEmpty())
<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <h2 class="font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ $heading }}
        </h2>
        @if($subheading)
            <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
        @endif
    </div>

    <div data-reveal-group class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($videos as $video)
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
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-10 text-center" data-reveal>
        <a href="{{ route('video-library.index') }}" class="btn-warm-outline">
            {{ __('videos.library.view_all') }}
        </a>
    </div>
</section>
@endif
