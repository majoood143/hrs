@php
    $tones = ['leading' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'won' => 'bg-warm-900 text-white ring-warm-200', 'outbid' => 'bg-amber-50 text-amber-700 ring-amber-100', 'lost' => 'bg-warm-50 text-warm-700 ring-warm-100'];
@endphp
<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('account.orders') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />{{ __('account.back_to_orders') }}
        </a>
        <p class="section-eyebrow mt-6">{{ __('account.eyebrow') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <h1 class="font-display text-3xl font-semibold text-warm-900">{{ __('auctions.site.account_title') }}</h1>
            <a href="{{ route('auctions.sell') }}" class="btn-warm-outline"><x-heroicon-o-plus-circle class="h-5 w-5" aria-hidden="true" />{{ __('auctions.sell.cta') }}</a>
        </div>

        @if(session('status'))
            <div class="mt-6 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" aria-hidden="true" /><p>{{ session('status') }}</p>
            </div>
        @endif

        @if($applications->isNotEmpty() || $selling->isNotEmpty())
            <section class="mt-8" aria-labelledby="my-horses">
                <h2 id="my-horses" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900"><x-heroicon-o-tag class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.sell.my_horses') }}</h2>
                <ul class="mt-4 divide-y divide-warm-100 rounded-2xl bg-white ring-1 ring-warm-100">
                    @foreach($selling as $lot)
                        @php
                            $result = match ($lot->status) {
                                'sold' => $lot->payout_paid_at ? 'paid_out' : ($lot->invoice?->isPaid() ? 'buyer_paid' : 'sold'),
                                'unsold' => 'unsold',
                                'withdrawn' => 'withdrawn',
                                default => 'live',
                            };
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <a href="{{ $lot->url() }}" class="min-w-0">
                                <p class="font-semibold text-warm-900">{{ __('auctions.site.lot_number', ['number' => $lot->lot_number]) }} · {{ $lot->name }}</p>
                                <p class="text-xs text-warm-700">{{ $lot->auction->title }} · {{ trans_choice('auctions.site.bids_count', $lot->bids_count, ['count' => $lot->bids_count]) }}</p>
                            </a>
                            <div class="text-end">
                                <p class="text-sm font-semibold text-warm-900" dir="ltr">{{ \App\Models\SiteSetting::formatCurrency($lot->hammer_price ?? $lot->current_price ?? $lot->starting_price) }}</p>
                                <p class="text-xs text-warm-700">{{ __('auctions.sell.results.'.$result, ['amount' => \App\Models\SiteSetting::formatCurrency($lot->payout_amount)]) }}</p>
                            </div>
                        </li>
                    @endforeach
                    @foreach($applications->where('status', '!=', 'approved') as $application)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="font-semibold text-warm-900">{{ $application->horse_name }}</p>
                                <p class="text-xs text-warm-700">{{ __('auctions.sell.sent_on', ['date' => $application->created_at->translatedFormat('j M Y')]) }}</p>
                                @if($application->status === 'rejected' && $application->reject_reason)
                                    <p class="mt-1 text-xs text-rose-700">{{ $application->reject_reason }}</p>
                                @endif
                            </div>
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold ring-4', 'bg-amber-50 text-amber-700 ring-amber-100' => $application->status === 'pending', 'bg-rose-50 text-rose-700 ring-rose-100' => $application->status === 'rejected'])>{{ __('auctions.application_statuses.'.$application->status) }}</span>
                        </li>
                    @endforeach
                    @foreach($applications->where('status', 'approved')->filter(fn ($a) => ! $selling->contains('id', $a->auction_lot_id)) as $application)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="font-semibold text-warm-900">{{ $application->horse_name }}</p>
                                <p class="text-xs text-warm-700">{{ __('auctions.sell.preparing', ['auction' => $application->lot?->auction?->title]) }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-4 ring-emerald-100">{{ __('auctions.application_statuses.approved') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($registrations->isEmpty() && $applications->isEmpty() && $selling->isEmpty())
            <div class="card-warm mt-8 flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-warm-50 text-warm-600 ring-4 ring-warm-100"><x-heroicon-o-megaphone class="h-7 w-7" aria-hidden="true" /></span>
                <p class="text-sm text-warm-700">{{ __('auctions.site.account_none') }}</p>
                <a href="{{ route('auctions.index') }}" class="btn-warm"><x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.browse') }}</a>
            </div>
        @elseif($registrations->isNotEmpty())
            <section class="mt-8" aria-labelledby="my-lots">
                <h2 id="my-lots" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900"><x-heroicon-o-hand-raised class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.my_bids') }}</h2>
                @if($lots->isEmpty())
                    <p class="mt-3 text-sm text-warm-700">{{ __('auctions.site.no_my_bids') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-warm-100 rounded-2xl bg-white ring-1 ring-warm-100">
                        @foreach($lots as $lot)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <a href="{{ $lot->url() }}" class="min-w-0">
                                    <p class="font-semibold text-warm-900">{{ __('auctions.site.lot_number', ['number' => $lot->lot_number]) }} · {{ $lot->name }}</p>
                                    <p class="text-xs text-warm-700">{{ $lot->auction->title }}</p>
                                </a>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold text-warm-900" dir="ltr">{{ \App\Models\SiteSetting::formatCurrency($lot->hammer_price ?? $lot->current_price) }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-4 {{ $tones[$lot->my_status] }}">{{ __('auctions.site.you_states.'.$lot->my_status) }}</span>
                                    @if($lot->my_status === 'won' && $lot->invoice?->isPayable())
                                        <a href="{{ route('payment.start', $lot->invoice->order_number) }}" class="btn-warm !px-3 !py-1.5 text-sm"><x-heroicon-o-credit-card class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.pay_invoice') }}</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if($watches->isNotEmpty())
                <section class="mt-10" aria-labelledby="my-watchlist">
                    <h2 id="my-watchlist" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900"><x-heroicon-o-bookmark class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.watchlist') }}</h2>
                    <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($watches as $watch)
                            @include('site.auctions._lot-card', ['lot' => $watch->lot])
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="mt-10" aria-labelledby="my-registrations">
                <h2 id="my-registrations" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900"><x-heroicon-o-identification class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.registrations') }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach($registrations as $registration)
                        <li class="card-warm p-5">
                            <a href="{{ route('auctions.show', $registration->auction->slug) }}" class="font-display text-lg font-semibold text-warm-900 hover:text-warm-700">{{ $registration->auction->title }}</a>
                            <p class="mt-1 text-sm text-warm-700">{{ __('auctions.site.register_states.'.$registration->status) }}@if($registration->status === 'approved') · {{ __('auctions.site.your_number', ['number' => $registration->paddle_number]) }}@endif</p>
                            @if($registration->depositOrder)
                                <a href="{{ route('account.orders.show', $registration->depositOrder->order_number) }}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-warm-700 hover:text-warm-900"><x-heroicon-o-banknotes class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.deposit_order', ['number' => $registration->depositOrder->order_number]) }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.site>
