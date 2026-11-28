@php $input = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300'; @endphp

<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-3xl px-6 py-14">
        <a href="{{ route('auctions.show', $auction->slug) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-warm-600 hover:text-warm-800">
            <x-heroicon-o-arrow-left class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />{{ $auction->title }}
        </a>

        <p class="section-eyebrow mt-6">{{ __('auctions.site.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('auctions.site.register_title') }}</h1>
        <p class="mt-2 text-warm-700">{{ __('auctions.site.register_intro', ['auction' => $auction->title]) }}</p>

        @if($bidder)
            <div class="mt-6">@include('site.auctions._register-box', ['auction' => $auction, 'bidder' => $bidder])</div>
        @endif

        @if($payable)
            <div class="card-warm mt-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm text-warm-900"><x-heroicon-o-banknotes class="h-5 w-5 text-warm-600" aria-hidden="true" />{{ __('auctions.site.deposit_waiting', ['amount' => \App\Models\SiteSetting::formatCurrency($payable->total)]) }}</p>
                <a href="{{ route('payment.start', $payable->order_number) }}" class="btn-warm shrink-0"><x-heroicon-o-credit-card class="h-5 w-5" aria-hidden="true" />{{ __('auctions.site.pay_deposit') }}</a>
            </div>
        @elseif(! in_array($bidder?->status, ['pending', 'rejected', 'blocked'], true))
            <form method="POST" action="{{ route('auctions.register.store', $auction->slug) }}" class="card-warm mt-6 space-y-5 p-6">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="reg-name" class="text-sm font-semibold text-warm-900">{{ __('auctions.site.full_name') }}</label>
                        <input id="reg-name" name="name" value="{{ old('name', $customer->name) }}" @if(! $customer->name) required @endif maxlength="255" placeholder="{{ __('auctions.site.full_name_placeholder') }}" class="{{ $input }} mt-1">
                        @error('name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="reg-id" class="text-sm font-semibold text-warm-900">{{ __('auctions.site.national_id') }}</label>
                        @if($customer->national_id)
                            <p class="mt-2 flex items-center gap-1.5 text-sm text-warm-700"><x-heroicon-o-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />{{ __('auctions.site.national_id_on_file', ['last' => $customer->maskedNationalId()]) }}</p>
                        @else
                            <input id="reg-id" name="national_id" value="{{ old('national_id') }}" required pattern="[A-Za-z0-9]{4,20}" dir="ltr" placeholder="{{ __('auctions.site.national_id_placeholder') }}" class="{{ $input }} mt-1">
                            <p class="mt-1 text-xs text-warm-700">{{ __('auctions.site.national_id_help') }}</p>
                        @endif
                        @error('national_id')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if($conditions = $auction->conditionsHtml())
                    <div class="max-h-64 overflow-y-auto rounded-xl border border-warm-200 bg-white p-4 text-sm"><div class="prose prose-sm max-w-none">{!! $conditions !!}</div></div>
                @endif

                @if((float) $auction->deposit_amount > 0)
                    <p class="flex gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900"><x-heroicon-o-banknotes class="h-5 w-5 shrink-0" aria-hidden="true" />{{ __('auctions.site.deposit_explained', ['amount' => \App\Models\SiteSetting::formatCurrency($auction->deposit_amount)]) }}</p>
                @endif

                <label class="flex items-start gap-3 text-sm text-warm-900">
                    <input type="checkbox" name="accept" value="1" required class="mt-0.5 h-5 w-5 rounded border-warm-300 text-warm-700 focus:ring-warm-500">
                    <span>{{ __('auctions.site.accept_conditions') }}</span>
                </label>
                @error('accept')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

                <button type="submit" class="btn-warm w-full justify-center sm:w-auto">
                    <x-heroicon-o-hand-raised class="h-5 w-5" aria-hidden="true" />{{ (float) $auction->deposit_amount > 0 ? __('auctions.site.register_and_pay') : __('auctions.site.register_submit') }}
                </button>
            </form>
        @endif
    </div>
</x-layouts.site>
