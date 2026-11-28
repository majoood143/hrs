@php
    $photos = $lot->photoUrls();
    $approved = $bidder?->status === 'approved';
    $zone = \App\Models\BookingSlot::timezone();
    $facts = array_filter([
        __('auctions.site.facts.gender') => $lot->gender ? __('auctions.genders.'.$lot->gender) : null,
        __('auctions.site.facts.born') => $lot->born_on?->translatedFormat('j M Y'),
        __('auctions.site.facts.breed') => $lot->breed,
        __('auctions.site.facts.colour') => $lot->colour ? (\App\Models\Stallion::colourOptions()[$lot->colour] ?? null) : null,
        __('auctions.site.facts.height') => $lot->height_cm ? __('auctions.site.height_value', ['cm' => $lot->height_cm, 'hands' => number_format($lot->height_cm / 10.16, 1)]) : null,
        __('auctions.site.facts.ueln') => $lot->ueln,
        __('auctions.site.facts.microchip') => $lot->microchip,
        __('auctions.site.facts.genetic_tests') => $lot->genetic_tests,
        __('auctions.site.facts.owner') => trim($lot->owner_name.($lot->owner_town ? ', '.$lot->owner_town : '')) ?: null,
        __('auctions.site.facts.breeder') => trim($lot->breeder_name.($lot->breeder_town ? ', '.$lot->breeder_town : '')) ?: null,
        __('auctions.site.facts.clinic') => $lot->clinic,
        __('auctions.site.facts.vat') => $lot->vat_rate !== null ? rtrim(rtrim($lot->vat_rate, '0'), '.').'%' : null,
    ]);
    $money = \App\Models\SiteSetting::moneyFormat();
    $costs = [
        'premium' => (float) $auction->buyer_premium_percent,
        'vat' => (float) $lot->vat_rate,
        'fee_vat' => app(\App\Services\Orders\OrderPricing::class)->vatRate(),
    ];
    $labels = [
        'ended' => __('auctions.site.closed'),
        'confirm' => __('auctions.site.confirm_bid'),
        'confirm_max' => __('auctions.site.confirm_max'),
        'error' => __('auctions.site.bid_failed'),
        'you' => __('auctions.site.you_states'),
        'reserve' => __('auctions.site.reserve_states'),
        'bids' => __('auctions.site.bids_short'),
        'bidders' => __('auctions.site.bidders_short'),
        'watch' => __('auctions.site.watch'),
        'watching' => __('auctions.site.watching'),
        'yours' => __('auctions.site.your_bid'),
    ];
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription" :seo-image="$seoImage">
    <div class="mx-auto max-w-7xl px-6 py-10">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm text-warm-700" aria-label="{{ __('auctions.site.breadcrumb') }}">
            <a href="{{ route('auctions.index') }}" class="font-semibold hover:text-warm-900">{{ __('auctions.site.title') }}</a>
            <x-heroicon-o-chevron-right class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            <a href="{{ route('auctions.show', $auction->slug) }}" class="font-semibold hover:text-warm-900">{{ $auction->title }}</a>
            <x-heroicon-o-chevron-right class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            <span class="text-warm-900">{{ $lot->name }}</span>
        </nav>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('auctions.show', $auction->slug) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
                <x-heroicon-o-list-bullet class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.lot_list') }}
            </a>
            <div class="flex gap-2">
                @if($previous)
                    <a href="{{ $previous->url() }}" class="btn-warm-outline !px-3 !py-1.5 text-sm"><x-heroicon-o-chevron-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />{{ __('auctions.site.lot_number', ['number' => $previous->lot_number]) }}</a>
                @endif
                @if($next)
                    <a href="{{ $next->url() }}" class="btn-warm-outline !px-3 !py-1.5 text-sm">{{ __('auctions.site.lot_number', ['number' => $next->lot_number]) }}<x-heroicon-o-chevron-right class="h-4 w-4 rtl:rotate-180" aria-hidden="true" /></a>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
            {{-- the horse --}}
            <div class="min-w-0 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3 rounded-t-3xl bg-warm-900 px-5 py-4 text-white">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">{{ __('auctions.site.lot_number', ['number' => $lot->lot_number]) }}</span>
                            <h1 class="font-display text-2xl font-semibold sm:text-3xl">{{ $lot->name }}</h1>
                        </div>
                        @if($lot->tagline)
                            <p class="mt-1 text-sm text-white/75">{{ $lot->tagline }}</p>
                        @endif
                        @if($lot->sire || $lot->dam)
                            <p class="mt-1 text-sm font-semibold text-white/90">{{ trim($lot->sire.' × '.$lot->dam, ' ×') }}</p>
                        @endif
                    </div>
                    @include('site.auctions._lot-status', ['lot' => $lot])
                </div>

                <div class="grid gap-1 sm:grid-cols-4">
                    <x-zoomable-image :src="$photos[0] ?? null" :alt="$lot->name" group="lot" class="h-80 sm:h-[28rem] {{ count($photos) > 1 ? 'sm:col-span-3' : 'sm:col-span-4' }}" />
                    <div class="hidden flex-col gap-1 sm:flex {{ count($photos) > 1 ? '' : 'sm:hidden' }}">
                        @foreach(array_slice($photos, 1, 3) as $photo)
                            <x-zoomable-image :src="$photo" :alt="$lot->name" group="lot" fit="cover" class="h-1/3 min-h-0 flex-1" />
                        @endforeach
                    </div>
                </div>
                @if(count($photos) > 1)
                    <div class="mt-1 grid grid-cols-4 gap-1 sm:hidden">
                        @foreach(array_slice($photos, 1) as $photo)
                            <x-zoomable-image :src="$photo" :alt="$lot->name" group="lot-mobile" fit="cover" class="h-20" />
                        @endforeach
                    </div>
                @endif

                <div class="mt-8 grid grid-cols-1 gap-8 {{ $lot->videos ? 'md:grid-cols-2' : '' }}">
                    @if($lot->videos)
                    <div>
                            <h2 class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900"><x-heroicon-o-play-circle class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.videos') }}</h2>
                            <div class="mt-3 space-y-2">
                                @foreach($lot->videos as $i => $video)
                                    @php $youtubeId = \App\Support\YouTube::videoId($video); @endphp
                                    <a href="{{ $video }}" target="_blank" rel="noopener noreferrer" @if($youtubeId) data-youtube-id="{{ $youtubeId }}" data-close-label="{{ __('auctions.site.close_video') }}" @endif
                                       class="group relative block overflow-hidden rounded-2xl bg-warm-900">
                                        @if($youtubeId)
                                            <img src="https://i.ytimg.com/vi/{{ $youtubeId }}/hqdefault.jpg" alt="" loading="lazy" class="aspect-video w-full object-cover opacity-80 transition group-hover:opacity-100">
                                        @endif
                                        <span class="absolute inset-0 flex items-center justify-center gap-2 text-sm font-semibold text-white"><x-heroicon-s-play-circle class="h-12 w-12" aria-hidden="true" /></span>
                                        <span class="sr-only">{{ __('auctions.site.watch_video', ['number' => $i + 1]) }}</span>
                                    </a>
                                @endforeach
                            </div>
                    </div>
                    @endif

                    <div>
                        <dl class="divide-y divide-warm-100 rounded-2xl bg-white text-sm ring-1 ring-warm-100">
                            @foreach($facts as $label => $value)
                                <div class="grid grid-cols-5 gap-3 px-4 py-2">
                                    <dt class="col-span-2 text-warm-900/60">{{ $label }}</dt>
                                    <dd class="col-span-3 font-medium text-warm-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if($lot->documents || $auction->conditionsHtml())
                            <div class="mt-4 rounded-2xl bg-warm-50 p-4">
                                <p class="flex items-center gap-2 text-sm font-semibold text-warm-900"><x-heroicon-o-document-text class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.documents') }}</p>
                                @if($approved)
                                    <ul class="mt-3 space-y-1.5">
                                        @foreach($lot->documents ?? [] as $index => $path)
                                            <li><a href="{{ route('auctions.document', ['auction' => $auction->slug, 'lot' => $lot->lot_number, 'index' => $index]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-700 hover:text-warm-900" target="_blank">
                                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" />{{ basename($path) }}</a></li>
                                        @endforeach
                                        <li><a href="{{ route('auctions.show', $auction->slug) }}#conditions-title" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-700 hover:text-warm-900"><x-heroicon-o-scale class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.conditions') }}</a></li>
                                    </ul>
                                @else
                                    <p class="mt-2 flex items-center gap-1.5 text-xs text-warm-700"><x-heroicon-o-lock-closed class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.documents_locked') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                @include('site.auctions._pedigree', ['lot' => $lot])

                @if($lot->dam_line || $lot->note)
                    <section class="mt-10 space-y-4">
                        @if($lot->note)
                            <p class="flex gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900"><x-heroicon-o-information-circle class="h-5 w-5 shrink-0" aria-hidden="true" />{{ $lot->note }}</p>
                        @endif
                        @if($lot->dam_line)
                            <div>
                                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('auctions.site.dam_line') }}</h2>
                                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $lot->dam_line }}</p>
                            </div>
                        @endif
                    </section>
                @endif

                @if($lot->racing_horse_id)
                    <a href="{{ route('racing.profile', ['entity' => 'horse', 'id' => $lot->racing_horse_id]) }}" class="btn-warm-outline mt-8 inline-flex"><x-heroicon-o-flag class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.race_record') }}</a>
                @endif
            </div>

            {{-- the bid board: kept live by resources/js/auction-live.js --}}
            <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start" aria-labelledby="board-title"
                   data-auction-lot
                   data-state-url="{{ route('auctions.lot.state', ['auction' => $auction->slug, 'lot' => $lot->lot_number]) }}"
                   data-bid-url="{{ route('auctions.bid', ['auction' => $auction->slug, 'lot' => $lot->lot_number]) }}"
                   data-watch-url="{{ route('auctions.watch', ['auction' => $auction->slug, 'lot' => $lot->lot_number]) }}"
                   data-server-now="{{ $state['server_now'] }}"
                   data-money="{{ json_encode($money) }}"
                   data-costs="{{ json_encode($costs) }}"
                   data-labels="{{ json_encode($labels) }}"
                   data-csrf="{{ csrf_token() }}">
                <div class="card-warm p-5">
                    <h2 id="board-title" class="flex items-center gap-2 font-display text-lg font-semibold text-warm-900"><x-heroicon-o-presentation-chart-line class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.bid_board') }}</h2>

                    <div class="mt-4 flex flex-wrap items-end justify-between gap-x-3 gap-y-1">
                        <div>
                            <p class="text-xs text-warm-900/60" data-price-label>{{ $lot->status === 'sold' ? __('auctions.site.hammer_price') : ($state['price'] ? __('auctions.site.current_bid') : __('auctions.site.starting_price')) }}</p>
                            <p class="font-display text-3xl font-semibold text-warm-900" dir="ltr" data-price>{{ $state['price'] ?? $state['starting'] }}</p>
                        </div>
                        <p data-reserve class="text-xs font-semibold {{ $state['reserve'] === 'met' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $state['reserve'] ? __('auctions.site.reserve_states.'.$state['reserve']) : '' }}</p>
                    </div>

                    <div class="mt-4 rounded-xl bg-warm-50 px-3 py-2 text-sm">
                        @if($lot->status === 'ready')
                            <p class="flex items-center justify-between gap-2"><span class="text-warm-900/60">{{ $state['not_started'] ? __('auctions.site.bidding_opens') : __('auctions.site.closes_in') }}</span>
                                @if($state['not_started'])
                                    <span class="font-semibold" dir="ltr">{{ $auction->bidding_starts_at->timezone($zone)->translatedFormat('j M, H:i') }}</span>
                                @else
                                    <span class="font-display text-xl font-semibold text-warm-900" data-countdown="{{ $state['ends_at'] }}" dir="ltr"></span>
                                @endif
                            </p>
                        @endif
                        <p class="mt-1 flex flex-wrap items-center justify-between gap-x-2 text-xs text-warm-900/60"><span>{{ __('auctions.site.ends_at') }}</span><span dir="ltr" data-ends-at>{{ $lot->ends_at?->timezone($zone)->translatedFormat('j M Y, H:i:s') }} (GMT+4)</span></p>
                    </div>

                    <p class="mt-3 flex justify-between text-sm text-warm-900/70" data-counts><span>{{ $state['bidders'] }} {{ __('auctions.site.bidders_short') }}</span><span>{{ $state['bids'] }} {{ __('auctions.site.bids_short') }}</span></p>

                    <p data-you class="mt-3 hidden rounded-xl px-3 py-2 text-sm font-semibold" role="status"></p>

                    @if($invoice = $state['invoice'])
                        <div class="mt-4 rounded-2xl bg-warm-900 p-4 text-white">
                            <p class="flex items-center gap-2 font-semibold"><x-heroicon-o-trophy class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.you_won_title') }}</p>
                            @if($invoice['paid'])
                                <p class="mt-2 flex items-center gap-1.5 text-sm text-white/85"><x-heroicon-o-check-circle class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.invoice_paid') }}</p>
                            @else
                                <p class="mt-2 text-sm text-white/85">{{ __('auctions.site.invoice_due', ['total' => $invoice['total'], 'due' => $invoice['due']]) }}</p>
                                @if($invoice['pay_url'])
                                    <a href="{{ $invoice['pay_url'] }}" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-4 py-2.5 text-sm font-bold text-warm-900"><x-heroicon-o-credit-card class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.pay_invoice') }}</a>
                                @endif
                                @if($auction->payment_note)
                                    <p class="mt-3 whitespace-pre-line rounded-xl bg-white/10 px-3 py-2 text-xs text-white/85"><span class="font-semibold">{{ __('auctions.site.bank_transfer') }}</span>
{{ $auction->payment_note }}</p>
                                @endif
                            @endif
                            <a href="{{ $invoice['order_url'] }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-white/80 hover:text-white"><x-heroicon-o-document-text class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.invoice_details') }}</a>
                        </div>
                    @endif

                    @if($lot->status === 'ready')
                        @auth('customer')
                            @if($approved)
                                <form data-bid-form class="mt-4 space-y-3" method="POST" action="{{ route('auctions.bid', ['auction' => $auction->slug, 'lot' => $lot->lot_number]) }}" @if(! $state['open']) hidden @endif>
                                    @csrf
                                    <label class="block text-sm font-semibold text-warm-900" for="bid-amount">{{ __('auctions.site.your_bid_label') }}</label>
                                    <div class="flex gap-2">
                                        <input id="bid-amount" name="amount" type="number" inputmode="decimal" step="0.001" min="{{ $state['minimum'] }}" value="{{ old('amount', $state['minimum']) }}" required dir="ltr"
                                               class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-lg font-semibold text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300" placeholder="{{ $state['minimum'] }}">
                                    </div>
                                    <p class="text-xs text-warm-900/60">{{ __('auctions.site.minimum_bid') }} <span dir="ltr" data-minimum>{{ $state['minimum_label'] }}</span></p>
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach([1, 2, 5] as $steps)
                                            <button type="button" data-quick-bid="{{ $steps }}" class="rounded-xl bg-warm-100 px-2 py-2 text-sm font-semibold text-warm-900 hover:bg-warm-200">+{{ $steps }} {{ __('auctions.site.steps') }}</button>
                                        @endforeach
                                    </div>
                                    <details class="rounded-xl bg-warm-50 px-3 py-2 text-sm">
                                        <summary class="cursor-pointer font-semibold text-warm-900">{{ __('auctions.site.set_maximum') }}</summary>
                                        <label class="mt-2 block text-xs text-warm-900/70" for="bid-max">{{ __('auctions.site.maximum_help') }}</label>
                                        <input id="bid-max" name="max_amount" type="number" inputmode="decimal" step="0.001" dir="ltr" placeholder="{{ __('auctions.site.maximum_placeholder') }}"
                                               class="mt-1 w-full rounded-xl border border-warm-200 bg-white px-3 py-2 text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                                    </details>
                                    <p class="text-xs text-warm-900/70">{{ __('auctions.site.total_if_win') }} <span class="font-semibold text-warm-900" dir="ltr" data-total></span></p>
                                    @error('amount')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                                    <button type="submit" class="btn-warm w-full justify-center"><x-heroicon-o-hand-raised class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.place_bid') }}</button>
                                    <p class="text-[11px] leading-snug text-warm-900/60">{{ __('auctions.site.binding_note') }}</p>
                                </form>
                            @else
                                <div class="mt-4">@include('site.auctions._register-box', ['auction' => $auction, 'bidder' => $bidder])</div>
                            @endif
                            <button type="button" data-watch-toggle aria-pressed="{{ $watching ? 'true' : 'false' }}" class="btn-warm-outline mt-3 w-full justify-center">
                                <x-heroicon-o-bookmark class="h-5 w-5" aria-hidden="true" /><span>{{ $watching ? __('auctions.site.watching') : __('auctions.site.watch') }}</span>
                            </button>
                        @else
                            <a href="{{ route('auctions.register', $auction->slug) }}" class="btn-warm mt-4 w-full justify-center"><x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.sign_in_to_bid') }}</a>
                        @endauth
                    @endif
                </div>

                <div class="card-warm mt-4 p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-warm-900"><x-heroicon-o-clock class="h-4 w-4 text-warm-600" aria-hidden="true" />{{ __('auctions.site.history') }}</h2>
                    <ol data-history class="mt-3 space-y-1.5 text-sm">
                        @forelse($state['history'] as $row)
                            <li class="flex justify-between gap-3 {{ $row['mine'] ? 'font-semibold text-warm-900' : 'text-warm-900/70' }}"><span>{{ $row['mine'] ? __('auctions.site.your_bid') : $row['bidder'] }}</span><span dir="ltr">{{ $row['amount'] }} · {{ $row['at'] }}</span></li>
                        @empty
                            <li class="text-warm-900/60">{{ __('auctions.site.no_bids') }}</li>
                        @endforelse
                    </ol>
                </div>
            </aside>
        </div>

        @if($suggestions->isNotEmpty())
            <section class="mt-16" aria-labelledby="more-title">
                <h2 id="more-title" class="font-display text-2xl font-semibold text-warm-900">{{ __('auctions.site.other_lots') }}</h2>
                <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($suggestions as $other)
                        @include('site.auctions._lot-card', ['lot' => $other])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-14"><x-share-buttons :url="url()->current()" :title="$lot->name" /></div>
    </div>
</x-layouts.site>
