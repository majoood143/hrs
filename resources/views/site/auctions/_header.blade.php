{{-- The auction's banner: its organiser's colour and logo ("Hosted by"), or the site's own; the dates and the phase. --}}
@php
    $organizer = $auction->organizer;
    $phase = $auction->phase();
    $zone = \App\Models\BookingSlot::timezone();
    $dates = [
        ['icon' => 'heroicon-o-book-open', 'label' => __('auctions.site.catalogue_online'), 'at' => $auction->preview_at],
        ['icon' => 'heroicon-o-play', 'label' => __('auctions.site.bidding_opens'), 'at' => $auction->bidding_starts_at],
        ['icon' => 'heroicon-o-flag', 'label' => __('auctions.site.first_lot_closes'), 'at' => $auction->ends_at],
    ];
@endphp

<section class="overflow-hidden rounded-3xl text-white shadow-xl {{ $organizer?->colour ? '' : 'bg-warm-900' }}" @if($organizer?->colour) style="background-color: {{ $organizer->colour }}" @endif>
    <div class="grid lg:grid-cols-5">
        <div class="relative min-h-56 lg:col-span-3">
            @if($auction->cover_url)
                <img src="{{ $auction->cover_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
            @endif
            <div class="relative flex h-full flex-col justify-end gap-2 p-6 sm:p-8">
                @include('site.auctions._phase', ['phase' => $phase, 'class' => 'self-start'])
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">{{ $auction->title }}</h1>
                @if($auction->description)
                    <p class="max-w-xl text-sm text-white/80">{{ $auction->description }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-col justify-between gap-6 p-6 sm:p-8 lg:col-span-2">
            <div class="flex items-center gap-3">
                @if($organizer?->logo_url)
                    <img src="{{ $organizer->logo_url }}" alt="" class="h-12 w-12 rounded-xl bg-white object-contain p-1">
                @endif
                <div>
                    <p class="text-xs uppercase tracking-wider text-white/60">{{ __('auctions.site.hosted_by') }}</p>
                    <p class="font-display text-lg font-semibold">{{ $organizer?->name ?? \App\Models\SiteSetting::siteName() }}</p>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                @foreach($dates as $date)
                    @continue(! $date['at'])
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-white/10 px-3 py-2">
                        <dt class="flex items-center gap-2 text-white/75">
                            <x-dynamic-component :component="$date['icon']" class="h-4 w-4" aria-hidden="true" />{{ $date['label'] }}
                        </dt>
                        <dd class="font-semibold" dir="ltr">{{ $date['at']->timezone($zone)->translatedFormat('j M Y, H:i') }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-xs text-white/60">{{ __('auctions.site.times_in', ['zone' => 'GMT+4']) }}</p>
        </div>
    </div>
</section>
