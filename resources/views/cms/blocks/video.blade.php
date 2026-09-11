@php
    $videoId = \App\Support\YouTube::videoId($data['url'] ?? null);
    $caption = \App\Support\Localized::value($data, 'caption');
@endphp

@if($videoId)
<figure data-reveal class="mx-auto max-w-5xl px-6 py-10">
    <div class="aspect-video overflow-hidden rounded-3xl shadow-lg shadow-warm-900/10">
        <iframe
            class="h-full w-full"
            src="https://www.youtube-nocookie.com/embed/{{ $videoId }}"
            title="{{ $caption ?: 'YouTube video player' }}"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
        ></iframe>
    </div>
    @if($caption)
        <figcaption class="mt-3 text-center text-sm text-warm-900/60">{{ $caption }}</figcaption>
    @endif
</figure>
@endif
