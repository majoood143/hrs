@php
    $heading = \App\Support\Localized::value($data, 'heading');
    $subheading = \App\Support\Localized::value($data, 'subheading');
    $columns = (int) ($data['columns'] ?? 1);
    $count = (int) ($data['count'] ?? 8);
    $autoplay = ($data['autoplay'] ?? true) ? 'true' : 'false';
    $autoplayDelay = (int) ($data['autoplay_delay'] ?? 5000);
    $fullBleed = $columns === 1;
    $mediaHeight = $fullBleed ? 'h-72 sm:h-96' : 'h-48 sm:h-64';

    $ads = \App\Models\Ad::query()->active()
        ->when(! empty($data['zone_id']), fn ($q) => $q->where('zone_id', $data['zone_id']))
        ->orderBy('order')
        ->take($count)
        ->get();
@endphp

@if($ads->isNotEmpty())
<section data-promo-slider data-columns="{{ $columns }}" data-autoplay="{{ $autoplay }}" data-autoplay-delay="{{ $autoplayDelay }}"
    class="{{ $fullBleed ? 'relative overflow-hidden' : 'mx-auto max-w-7xl px-6' }} py-16">

    @if($heading)
        <div class="mx-auto max-w-2xl text-center pb-8 {{ $fullBleed ? 'px-6' : '' }}" data-reveal>
            <h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">{{ $heading }}</h2>
            @if($subheading)
                <p class="mt-2 text-warm-900/70">{{ $subheading }}</p>
            @endif
        </div>
    @endif

    <div class="relative {{ $fullBleed ? 'px-6' : '' }}">
        <div class="swiper promo-swiper">
            <div class="swiper-wrapper">
                @foreach($ads as $ad)
                    @php
                        $link = $ad->target_url ? route('promo.click', $ad) : null;
                        $alt = $ad->alt_text ?: $ad->customer_name;
                    @endphp
                    <div class="swiper-slide">
                        @if($link)
                            <a href="{{ $link }}" target="_blank" rel="noopener sponsored" class="block overflow-hidden rounded-2xl">
                        @else
                            <div class="overflow-hidden rounded-2xl">
                        @endif

                            @if($ad->media_type === 'video' && $ad->video_path)
                                <video data-promo-video src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ad->video_path) }}"
                                    muted loop playsinline preload="metadata" class="{{ $mediaHeight }} w-full object-cover"></video>
                            @elseif($ad->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ad->image_path) }}"
                                    alt="{{ $alt }}" class="{{ $mediaHeight }} w-full object-cover">
                            @endif

                        @if($link)
                            </a>
                        @else
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($ads->count() > 1)
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            @endif
        </div>
    </div>
</section>
@endif
