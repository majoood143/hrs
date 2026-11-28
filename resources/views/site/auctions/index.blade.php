<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription">
    <div class="mx-auto max-w-7xl px-6 py-14">
        <div class="max-w-2xl" data-reveal>
            <p class="section-eyebrow flex items-center gap-2"><x-heroicon-o-megaphone class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('auctions.site.title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('auctions.site.subtitle') }}</p>
            <a href="{{ route('auctions.sell') }}" class="btn-warm-outline mt-5 inline-flex"><x-heroicon-o-tag class="h-5 w-5" aria-hidden="true" />{{ __('auctions.sell.cta') }}</a>
        </div>

        @if($groups->flatten()->isEmpty())
            <div class="card-warm mt-10 flex flex-col items-center gap-3 p-12 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-warm-50 text-warm-600 ring-4 ring-warm-100"><x-heroicon-o-megaphone class="h-7 w-7" aria-hidden="true" /></span>
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('auctions.site.none_title') }}</p>
                <p class="text-sm text-warm-700">{{ __('auctions.site.none_body') }}</p>
            </div>
        @endif

        @foreach($groups as $phase => $auctions)
            @continue($auctions->isEmpty())
            <section class="mt-12" aria-labelledby="phase-{{ $phase }}">
                <h2 id="phase-{{ $phase }}" class="flex items-center gap-2 font-display text-2xl font-semibold text-warm-900">
                    @include('site.auctions._phase', ['phase' => $phase])
                    <span>{{ __('auctions.site.groups.'.$phase) }}</span>
                </h2>
                <div class="mt-5 grid gap-6 md:grid-cols-2">
                    @foreach($auctions as $auction)
                        <a href="{{ route('auctions.show', $auction->slug) }}" class="card-warm group block overflow-hidden p-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-500">
                            @if($auction->cover_url)
                                <img src="{{ $auction->cover_url }}" alt="" loading="lazy" class="h-52 w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-52 w-full items-center justify-center bg-warm-100 text-warm-300" aria-hidden="true"><x-heroicon-o-megaphone class="h-12 w-12" /></div>
                            @endif
                            <div class="p-5">
                                <p class="text-xs text-warm-900/60">{{ __('auctions.site.hosted_by') }} {{ $auction->organizer?->name ?? \App\Models\SiteSetting::siteName() }}</p>
                                <p class="mt-1 font-display text-xl font-semibold text-warm-900 group-hover:text-warm-700">{{ $auction->title }}</p>
                                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-warm-900/70">
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-rectangle-stack class="h-4 w-4" aria-hidden="true" />{{ trans_choice('auctions.site.lots_count', $auction->public_lots_count, ['count' => $auction->public_lots_count]) }}</span>
                                    <span class="flex items-center gap-1.5"><x-heroicon-o-calendar class="h-4 w-4" aria-hidden="true" />
                                        <span dir="ltr">{{ $auction->bidding_starts_at?->timezone(\App\Models\BookingSlot::timezone())->translatedFormat('j M') }} – {{ $auction->ends_at?->timezone(\App\Models\BookingSlot::timezone())->translatedFormat('j M Y') }}</span>
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.site>
