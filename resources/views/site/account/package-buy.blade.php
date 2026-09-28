@php
    $money = fn ($baisa) => \App\Models\SiteSetting::formatCurrencyHtml($baisa / 1000, 3);
@endphp
<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-2xl px-6 py-14">
        <a href="{{ route('bookings.offering', ['stable' => $package->stable->slug, 'offering' => $package->offering->id]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            {{ $package->offering->name }}
        </a>

        <p class="section-eyebrow mt-6 flex items-center gap-1.5"><x-heroicon-o-rectangle-stack class="h-4 w-4" aria-hidden="true" />{{ __('stable_packages.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ $package->name }}</h1>
        <p class="mt-1 text-sm text-warm-700">{{ $package->stable->name }}</p>

        @if(session('error'))
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        <div class="card-warm mt-6 p-6">
            <ul class="grid gap-3 text-sm text-warm-900 sm:grid-cols-3">
                <li class="flex items-center gap-2"><x-heroicon-o-ticket class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ trans_choice('stable_packages.sessions_count', $package->sessions, ['count' => $package->sessions]) }}</li>
                <li class="flex items-center gap-2"><x-heroicon-o-calendar class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ trans_choice('stable_packages.valid_days', $package->validity_days, ['count' => $package->validity_days]) }}</li>
                <li class="flex items-center gap-2"><x-heroicon-o-academic-cap class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ $package->offering->name }}</li>
            </ul>

            @if($package->description)
                <p class="mt-4 whitespace-pre-line text-sm text-warm-800">{{ $package->description }}</p>
            @endif

            <dl class="mt-5 space-y-1 border-t border-warm-100 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-warm-700">{{ $package->name }}</dt><dd dir="ltr">{!! $money($quote->price) !!}</dd></div>
                @if($quote->fee > 0)
                    <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.service_fee') }}</dt><dd dir="ltr">{!! $money($quote->fee) !!}</dd></div>
                @endif
                @if($quote->vatOnPrice + $quote->vatOnFee > 0)
                    <div class="flex justify-between"><dt class="text-warm-700">{{ __('orders.vat', ['rate' => rtrim(rtrim(number_format($quote->vatRate, 2), '0'), '.')]) }}</dt><dd dir="ltr">{!! $money($quote->vatOnPrice + $quote->vatOnFee) !!}</dd></div>
                @endif
                <div class="flex justify-between pt-1 text-base font-semibold text-warm-900"><dt>{{ __('orders.total') }}</dt><dd dir="ltr">{!! $money($quote->total()) !!}</dd></div>
            </dl>
            @if($package->savingBaisa() > 0)
                <p class="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                    <x-heroicon-o-sparkles class="h-5 w-5" aria-hidden="true" />{{ __('stable_packages.saving', ['amount' => \App\Support\Money::format($package->savingBaisa())]) }}
                </p>
            @endif

            <form method="POST" action="{{ route('account.packages.purchase', $package) }}" class="mt-6">
                @csrf
                <button type="submit" class="btn-warm w-full justify-center">
                    <x-heroicon-o-credit-card class="h-5 w-5" aria-hidden="true" />{{ __('stable_packages.buy_now') }}
                </button>
                <p class="mt-3 text-xs text-warm-700">{{ __('stable_packages.terms', ['days' => $package->validity_days]) }}</p>
            </form>
        </div>
    </div>
</x-layouts.site>
