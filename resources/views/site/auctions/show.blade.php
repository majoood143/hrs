@php $input = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300'; @endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription" :seo-image="$seoImage">
    <div class="mx-auto max-w-7xl px-6 py-10">
        <nav class="mb-6 flex items-center gap-1.5 text-sm text-warm-700" aria-label="{{ __('auctions.site.breadcrumb') }}">
            <a href="{{ route('auctions.index') }}" class="font-semibold hover:text-warm-900">{{ __('auctions.site.title') }}</a>
            <x-heroicon-o-chevron-right class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            <span class="text-warm-900">{{ $auction->title }}</span>
        </nav>

        @include('site.auctions._header', ['auction' => $auction])

        @if(session('status'))
            <div class="mt-6 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" aria-hidden="true" /><p>{{ session('status') }}</p>
            </div>
        @endif

        <div class="mt-6">@include('site.auctions._register-box', ['auction' => $auction, 'bidder' => $bidder])</div>

        <section class="mt-10" aria-labelledby="lots-title">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="lots-title" class="flex items-center gap-2 font-display text-2xl font-semibold text-warm-900">
                    <x-heroicon-o-rectangle-stack class="h-6 w-6 text-warm-600" aria-hidden="true" />{{ __('auctions.site.lots') }}
                </h2>
                <form method="GET" class="flex flex-col gap-2 sm:flex-row">
                    <label class="sr-only" for="lot-q">{{ __('auctions.site.search') }}</label>
                    <input id="lot-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('auctions.site.search_placeholder') }}" class="{{ $input }} sm:w-64">
                    <label class="sr-only" for="lot-gender">{{ __('auctions.site.gender') }}</label>
                    <select id="lot-gender" name="gender" data-auto-submit class="{{ $input }} sm:w-40">
                        <option value="">{{ __('auctions.site.any_gender') }}</option>
                        @foreach(\App\Models\AuctionLot::genderOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['gender'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-warm"><x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.search') }}</button>
                </form>
            </div>

            @if($lots->isEmpty())
                <div class="mt-6 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                    <p class="font-display text-xl font-semibold text-warm-900">{{ __('auctions.site.no_lots') }}</p>
                </div>
            @else
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-auction-lots data-state-url="{{ route('auctions.state', $auction->slug) }}" data-server-now="{{ now()->toIso8601String() }}" data-labels="{{ json_encode(['ended' => __('auctions.site.closed'), 'bids' => __('auctions.site.bids_short')]) }}">
                    @foreach($lots as $lot)
                        @include('site.auctions._lot-card', ['lot' => $lot])
                    @endforeach
                </div>
            @endif
        </section>

        @if($conditions = $auction->conditionsHtml())
            <section class="mt-14" aria-labelledby="conditions-title">
                <h2 id="conditions-title" class="flex items-center gap-2 font-display text-2xl font-semibold text-warm-900">
                    <x-heroicon-o-scale class="h-6 w-6 text-warm-600" aria-hidden="true" />{{ __('auctions.site.conditions') }}
                </h2>
                <div class="prose mt-4 max-w-none text-warm-900/80">{!! $conditions !!}</div>
            </section>
        @endif
    </div>
</x-layouts.site>
