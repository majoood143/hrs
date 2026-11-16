{{-- The stallion's photo, or a plain panel with his initial (the catalogue has no photo for some). --}}
@if($stallion->photo_url)
    <x-zoomable-image :src="$stallion->photo_url" alt="{{ $stallion->name }}" class="{{ $class }} object-cover" />
@else
    <div class="{{ $class }} flex items-center justify-center bg-warm-100 font-display text-5xl font-semibold text-warm-400" aria-hidden="true">
        {{ mb_substr($stallion->name, 0, 1) }}
    </div>
@endif
