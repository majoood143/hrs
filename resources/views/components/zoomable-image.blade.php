{{--
    A listing photo that opens the original, full size, in a lightbox (resources/js/site.js, lightbox()).
    fit="contain" (the default, for cover photos) shows the whole image over a blurred copy of itself,
    so tall posters are not cropped; fit="cover" is for small gallery thumbnails.
    Images sharing a `group` can be browsed with the lightbox arrows. Without JS the link opens the image.
--}}
@props(['src', 'alt' => '', 'fit' => 'contain', 'group' => null])

<a href="{{ $src }}" target="_blank" rel="noopener"
   data-lightbox="{{ $group ?? '' }}"
   data-lightbox-labels="{{ json_encode(['close' => __('listings.close_image'), 'prev' => __('listings.previous_image'), 'next' => __('listings.next_image')]) }}"
   aria-label="{{ __('listings.view_full_image') }}"
   {{ $attributes->class('group relative block overflow-hidden bg-warm-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-500 focus-visible:ring-offset-2') }}>
    @if($fit === 'contain')
        <img src="{{ $src }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-60 blur-2xl">
        <img src="{{ $src }}" alt="{{ $alt }}" class="relative h-full w-full object-contain">
    @else
        <img src="{{ $src }}" alt="{{ $alt }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
    @endif
    <span class="pointer-events-none absolute bottom-3 end-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-xs font-semibold text-white opacity-90 backdrop-blur transition group-hover:opacity-100">
        <x-heroicon-o-arrows-pointing-out class="h-4 w-4" aria-hidden="true" />
        @if($fit === 'contain')
            <span>{{ __('listings.view_full_image') }}</span>
        @endif
    </span>
</a>
