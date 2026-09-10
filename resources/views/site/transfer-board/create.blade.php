@php
    $errorStep = 0;
    if ($errors->hasAny(['from_city_id', 'to_city_id'])) {
        $errorStep = 1;
    } elseif ($errors->hasAny(['capacity', 'transfer_date', 'price', 'contact_number'])) {
        $errorStep = 2;
    } elseif ($errors->hasAny(['captcha'])) {
        $errorStep = 3;
    }
@endphp

<x-layouts.site :seo-title="$seoTitle">
    <div class="mx-auto max-w-3xl px-6 py-14 pb-28"
         data-wizard="transfer"
         data-error-step="{{ $errorStep }}"
         data-step-label-template="{{ __('transportation.step_of', ['current' => ':current', 'total' => ':total']) }}"
         data-budget-open-label="{{ __('transportation.budget_open') }}"
         data-currency-symbol="{{ \App\Models\SiteSetting::currency()['symbol'] }}">

        <div class="text-center" data-reveal>
            <p class="section-eyebrow">{{ __('transportation.nav_group') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                {{ __('transportation.post_page_title') }}
            </h1>
            <p class="mt-3 text-warm-900/70">{{ __('transportation.post_page_subtitle') }}</p>
        </div>

        <div class="mt-10">
            <p data-step-label class="text-center text-xs font-semibold uppercase tracking-wide text-warm-600"></p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-warm-100">
                <div data-progress-fill class="h-full rounded-full bg-warm-600 transition-all duration-300" style="width: 25%"></div>
            </div>
        </div>

        @if($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-semibold">{{ __('transportation.form_errors_title') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('transfer-board.store') }}" class="mt-8" novalidate>
            @csrf

            {{-- Step 1: Type --}}
            <div data-step>
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.step_type') }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="offer" class="peer sr-only" required {{ old('type', 'offer') === 'offer' ? 'checked' : '' }}>
                        <span class="block rounded-2xl border-2 border-warm-200 bg-white p-6 transition peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-warm-400">
                            <span class="text-3xl" aria-hidden="true">🚚</span>
                            <span data-card-title class="mt-3 block font-display text-lg font-semibold text-warm-900">{{ __('transportation.type_offer') }}</span>
                            <span class="mt-1 block text-sm text-warm-900/60">{{ __('transportation.type_offer_hint') }}</span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="request" class="peer sr-only" required {{ old('type') === 'request' ? 'checked' : '' }}>
                        <span class="block rounded-2xl border-2 border-warm-200 bg-white p-6 transition peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-focus-visible:ring-2 peer-focus-visible:ring-warm-400">
                            <span class="text-3xl" aria-hidden="true">🤝</span>
                            <span data-card-title class="mt-3 block font-display text-lg font-semibold text-warm-900">{{ __('transportation.type_request') }}</span>
                            <span class="mt-1 block text-sm text-warm-900/60">{{ __('transportation.type_request_hint') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Step 2: Route --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.route') }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.filter_from') }}</label>
                        <select name="from_city_id" required class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                            <option value="">{{ __('transportation.select_city') }}</option>
                            @foreach($countries as $country)
                                @php($cities = $country->region->flatMap->city)
                                @continue($cities->isEmpty())
                                <optgroup label="{{ $country->name }}">
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}" @selected((string) old('from_city_id') === (string) $city->id)>{{ $city->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.filter_to') }}</label>
                        <select name="to_city_id" required class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                            <option value="">{{ __('transportation.select_city') }}</option>
                            @foreach($countries as $country)
                                @php($cities = $country->region->flatMap->city)
                                @continue($cities->isEmpty())
                                <optgroup label="{{ $country->name }}">
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}" @selected((string) old('to_city_id') === (string) $city->id)>{{ $city->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Step 3: Details --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.step_details') }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.capacity') }}</label>
                        <input type="number" name="capacity" min="1" required value="{{ old('capacity', 1) }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.transfer_date') }}</label>
                        <input type="date" name="transfer_date" min="{{ now()->toDateString() }}" required value="{{ old('transfer_date') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                    <div data-price-field>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.price') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-warm-900/50"><x-currency-symbol /></span>
                            <input type="number" name="price" min="0" step="0.01" value="{{ old('price') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 py-3 pl-8 pr-4 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.contact_number') }}</label>
                        <input type="tel" name="contact_number" required value="{{ old('contact_number') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                </div>
            </div>

            {{-- Step 4: Review & verify --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.review_title') }}</h2>
                <dl class="mt-5 divide-y divide-warm-200 overflow-hidden rounded-2xl border border-warm-200 bg-white text-sm">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.step_type') }}</dt>
                        <dd data-review="type" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.route') }}</dt>
                        <dd data-review="route" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.capacity') }}</dt>
                        <dd data-review="capacity" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.transfer_date') }}</dt>
                        <dd data-review="date" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.price') }}</dt>
                        <dd data-review="price" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('transportation.contact_number') }}</dt>
                        <dd data-review="contact" class="font-semibold text-warm-900"></dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('transportation.captcha_label') }}</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <img
                            data-captcha-image
                            data-src="{{ route('transfer-board.captcha') }}"
                            src="{{ route('transfer-board.captcha') }}"
                            alt="{{ __('transportation.captcha_label') }}"
                            width="180" height="50"
                            class="h-[50px] w-[180px] rounded-lg border border-warm-200 bg-white"
                        >
                        <button type="button" data-captcha-refresh class="text-xs font-semibold text-warm-600 underline underline-offset-2">
                            {{ __('transportation.captcha_refresh') }}
                        </button>
                    </div>
                    <input
                        type="text" name="captcha" required autocomplete="off"
                        placeholder="{{ __('transportation.captcha_placeholder') }}"
                        class="mt-3 w-full max-w-xs rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300"
                    >
                </div>
            </div>

            <div class="sticky bottom-0 z-10 mt-8 flex items-center gap-3 border-t border-warm-200/70 bg-warm-50/95 py-4 backdrop-blur">
                <button type="button" data-back class="btn-warm-outline hidden">{{ __('transportation.back') }}</button>
                <div class="flex-1"></div>
                <button type="button" data-next class="btn-warm">{{ __('transportation.next') }}</button>
                <button type="submit" data-submit class="btn-warm hidden">{{ __('transportation.submit_post') }}</button>
            </div>
        </form>
    </div>
</x-layouts.site>
