{{-- A lot in a grid (catalogue, suggestions, account). Price and countdown are kept live by auction-live.js. --}}
@php
    $photo = $lot->photoUrls()[0] ?? null;
    $price = match (true) {
        $lot->status === 'sold' && $lot->hide_result_price => __('auctions.site.price_on_request'),
        $lot->status === 'sold' => \App\Models\SiteSetting::formatCurrency($lot->hammer_price),
        $lot->current_price !== null => \App\Models\SiteSetting::formatCurrency($lot->current_price),
        default => null,
    };
@endphp
<article data-lot-card="{{ $lot->lot_number }}" class="card-warm group flex flex-col overflow-hidden p-0">
    <a href="{{ $lot->url() }}" class="relative block focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-500">
        @if($photo)
            <img src="{{ $photo }}" alt="" loading="lazy" class="h-56 w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-56 w-full items-center justify-center bg-warm-100 text-warm-300" aria-hidden="true"><x-heroicon-o-photo class="h-12 w-12" /></div>
        @endif
        <span class="absolute start-3 top-3 rounded-full bg-warm-900/85 px-3 py-1 text-xs font-bold text-white backdrop-blur">{{ __('auctions.site.lot_number', ['number' => $lot->lot_number]) }}</span>
    </a>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <div class="flex items-start justify-between gap-3">
            <a href="{{ $lot->url() }}" class="font-display text-xl font-semibold text-warm-900 hover:text-warm-700">{{ $lot->name }}</a>
            @include('site.auctions._lot-status', ['lot' => $lot])
        </div>

        <p class="text-sm text-warm-900/70">
            {{ collect([$lot->gender ? __('auctions.genders.'.$lot->gender) : null, $lot->born_on ? __('auctions.site.born', ['year' => $lot->born_on->year]) : null])->filter()->implode(' · ') }}
        </p>
        @if($lot->sire || $lot->dam)
            <p class="text-sm text-warm-900/80">{{ trim($lot->sire.' × '.$lot->dam, ' ×') }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 border-t border-dashed border-warm-200 pt-3">
            <div>
                <p class="text-xs text-warm-900/60">{{ $lot->status === 'sold' ? __('auctions.site.hammer_price') : ($price ? __('auctions.site.current_bid') : __('auctions.site.starting_price')) }}</p>
                <p data-lot-price class="font-display text-lg font-semibold text-warm-900" dir="ltr">{{ $price ?? \App\Models\SiteSetting::formatCurrency($lot->starting_price) }}</p>
            </div>
            <div class="text-end text-xs text-warm-900/60">
                @if($lot->status === 'ready' && $lot->ends_at)
                    <p class="flex items-center justify-end gap-1 font-semibold text-warm-900"><x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" /><span data-countdown="{{ $lot->ends_at->toIso8601String() }}" dir="ltr"></span></p>
                @endif
                <p data-lot-counts>{{ trans_choice('auctions.site.bids_count', $lot->bids_count, ['count' => $lot->bids_count]) }}</p>
            </div>
        </div>
    </div>
</article>
