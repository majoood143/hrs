<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-4xl px-6 py-14">
        <a href="{{ route('account.orders') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            {{ __('account.back_to_orders') }}
        </a>

        <p class="section-eyebrow mt-6">{{ __('account.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('stable_packages.account.title') }}</h1>

        @if(session('status'))
            <div class="mt-6 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if($purchases->isEmpty())
            <div class="card-warm mt-8 flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-warm-50 text-warm-600 ring-4 ring-warm-100">
                    <x-heroicon-o-rectangle-stack class="h-7 w-7" aria-hidden="true" />
                </span>
                <p class="text-sm text-warm-700">{{ __('stable_packages.account.none') }}</p>
                <a href="{{ route('bookings.search') }}" class="btn-warm">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />{{ __('stable_packages.account.browse') }}
                </a>
            </div>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach($purchases as $purchase)
                    @php
                        $state = $purchase->statusKey();
                        $left = $purchase->sessionsLeft();
                    @endphp
                    <li class="card-warm flex flex-col gap-3 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-lg font-semibold text-warm-900">{{ $purchase->package?->name ?? $purchase->name }}</p>
                                <p class="text-sm text-warm-700">{{ $purchase->stable?->name }}</p>
                            </div>
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-4',
                                'bg-emerald-50 text-emerald-700 ring-emerald-100' => $state === 'active',
                                'bg-amber-50 text-amber-700 ring-amber-100' => $state === 'pending',
                                'bg-warm-50 text-warm-700 ring-warm-100' => ! in_array($state, ['active', 'pending'], true),
                            ])>{{ __('stable_packages.status.'.$state) }}</span>
                        </div>

                        @if($state === 'active')
                            <div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="font-semibold text-warm-900">{{ trans_choice('stable_packages.sessions_left', $left, ['count' => $left]) }}</span>
                                    <span class="text-warm-700">{{ __('stable_packages.of_total', ['total' => $purchase->sessions]) }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-warm-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $purchase->sessions }}" aria-valuenow="{{ $left }}">
                                    <div class="h-full rounded-full bg-warm-600" style="width: {{ $purchase->sessions ? round($left * 100 / $purchase->sessions) : 0 }}%"></div>
                                </div>
                                <p class="mt-2 flex items-center gap-1.5 text-xs text-warm-700">
                                    <x-heroicon-o-calendar class="h-4 w-4" aria-hidden="true" />{{ __('stable_packages.valid_until', ['date' => $purchase->expires_at->toDateString()]) }}
                                </p>
                            </div>
                            @if($left > 0 && $purchase->offering && $purchase->stable)
                                <a href="{{ route('bookings.offering', ['stable' => $purchase->stable->slug, 'offering' => $purchase->offering->id]) }}" class="btn-warm mt-auto justify-center !py-2 text-sm">
                                    <x-heroicon-o-calendar-days class="h-5 w-5" aria-hidden="true" />{{ __('stable_packages.account.book') }}
                                </a>
                            @endif
                        @elseif($state === 'pending' && $purchase->order?->isPayable())
                            <a href="{{ route('payment.start', $purchase->order->order_number) }}" class="btn-warm mt-auto justify-center !py-2 text-sm">
                                <x-heroicon-o-credit-card class="h-5 w-5" aria-hidden="true" />{{ __('orders.pay_now') }}
                            </a>
                        @endif
                        <p class="text-xs text-warm-500" dir="ltr">{{ $purchase->reference }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.site>
