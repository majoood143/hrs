@php
    $slides = $data['slides'] ?? [];
    $autoplay = ($data['autoplay'] ?? true) ? 'true' : 'false';
    $autoplayDelay = (int) ($data['autoplay_delay'] ?? 6000);
@endphp

@if(count($slides))
<section data-hero-slider data-autoplay="{{ $autoplay }}" data-autoplay-delay="{{ $autoplayDelay }}"
    class="relative z-0 overflow-hidden" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="swiper hero-swiper min-h-[70vh]">
        <div class="swiper-wrapper">
            @foreach($slides as $slide)
                @php
                    $heading = \App\Support\Localized::value($slide, 'heading') ?: __('Every journey, a little more like home');
                    $subheading = \App\Support\Localized::value($slide, 'subheading');
                    $primaryText = \App\Support\Localized::value($slide, 'primary_button_text');
                    $secondaryText = \App\Support\Localized::value($slide, 'secondary_button_text');
                    $hasImage = !empty($slide['background_image']);
                @endphp
                <div class="swiper-slide relative flex min-h-[70vh] items-center justify-center">
                    <div class="absolute inset-0 -z-10">
                        @if($hasImage)
                            <img data-hero-slide-image src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slide['background_image']) }}" alt="" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-b from-warm-950/70 via-warm-950/40 to-warm-50"></div>
                        @else
                            <div class="h-full w-full bg-gradient-to-br from-warm-200 via-warm-100 to-clay-50"></div>
                        @endif
                    </div>

                    <div class="mx-auto flex max-w-4xl flex-col items-center px-6 py-28 text-center {{ $hasImage ? 'text-white' : 'text-warm-950' }}">
                        {{-- <span data-hero-eyebrow class="section-eyebrow {{ $hasImage ? '!text-warm-200' : '' }}">
                            {{ __('A warmer way to move horses') }}
                        </span> --}}

                        <h1 data-hero-heading class="mt-4 text-balance font-display text-4xl font-semibold leading-tight sm:text-5xl md:text-6xl">
                            {{ $heading }}
                        </h1>

                        @if($subheading)
                            <p data-hero-sub class="mt-6 max-w-2xl text-balance text-lg leading-relaxed {{ $hasImage ? 'text-warm-50/90' : 'text-warm-900/80' }}">
                                {{ $subheading }}
                            </p>
                        @endif

                        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                            @if($primaryText)
                                <a data-hero-cta href="{{ $slide['primary_button_url'] ?? '#' }}" class="btn-warm">
                                    {{ $primaryText }}
                                </a>
                            @endif
                            @if($secondaryText)
                                <a data-hero-cta href="{{ $slide['secondary_button_url'] ?? '#' }}"
                                   class="{{ $hasImage ? 'inline-flex items-center justify-center gap-2 rounded-full border-2 border-white/70 px-6 py-3 font-semibold text-white transition hover:bg-white hover:text-warm-800' : 'btn-warm-outline' }}">
                                    {{ $secondaryText }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($slides) > 1)
            <div class="swiper-pagination" data-hero-pagination></div>
        @endif
    </div>
</section>
@endif
