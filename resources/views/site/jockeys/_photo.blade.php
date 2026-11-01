{{-- The jockey's photo, or a plain panel with their initial when they gave none (the photo is optional). --}}
@if($jockey->cover_photo_url)
    <x-zoomable-image :src="$jockey->cover_photo_url" alt="{{ $jockey->name }}" class="{{ $class }}" />
@else
    <div class="{{ $class }} flex items-center justify-center bg-warm-100 font-display text-5xl font-semibold text-warm-400" aria-hidden="true">
        {{ mb_substr($jockey->name, 0, 1) }}
    </div>
@endif
