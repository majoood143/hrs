@php
    $errorStep = 0;
    if ($errors->hasAny(['en_name', 'ar_name', 'category', 'condition', 'brand'])) {
        $errorStep = 0;
    } elseif ($errors->hasAny(['city_id'])) {
        $errorStep = 1;
    } elseif ($errors->hasAny(['cover_photo', 'price', 'description_en', 'description_ar', 'contact_number'])) {
        $errorStep = 2;
    } elseif ($errors->hasAny(['captcha'])) {
        $errorStep = 3;
    }
@endphp

<x-layouts.site :seo-title="$seoTitle">
    <div class="mx-auto max-w-3xl px-6 py-14 pb-28"
         data-wizard="tool-sale"
         data-error-step="{{ $errorStep }}"
         data-step-label-template="{{ __('tools-for-sale.step_of', ['current' => ':current', 'total' => ':total']) }}"
         data-currency-symbol="{{ \App\Models\SiteSetting::currency()['symbol'] }}">

        <div class="text-center" data-reveal>
            <p class="section-eyebrow">{{ __('tools-for-sale.nav_group') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                {{ __('tools-for-sale.post_page_title') }}
            </h1>
            <p class="mt-3 text-warm-900/70">{{ __('tools-for-sale.post_page_subtitle') }}</p>
        </div>

        <div class="mt-10">
            <p data-step-label class="text-center text-xs font-semibold uppercase tracking-wide text-warm-600"></p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-warm-100">
                <div data-progress-fill class="h-full rounded-full bg-warm-600 transition-all duration-300" style="width: 25%"></div>
            </div>
        </div>

        @if($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-semibold">{{ __('tools-for-sale.form_errors_title') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tools-for-sale.store') }}" class="mt-8" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Step 1: Tool info --}}
            <div data-step>
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('tools-for-sale.step_basic') }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.name_en') }}</label>
                        <input type="text" name="en_name" required value="{{ old('en_name') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.name_ar') }}</label>
                        <input type="text" name="ar_name" dir="rtl" required value="{{ old('ar_name') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.category') }}</label>
                        <select name="category" required class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                            <option value="">{{ __('tools-for-sale.select_option') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" @selected((string) old('category') === (string) $category)>{{ __('tools-for-sale.categories.' . $category) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.condition') }}</label>
                        <select name="condition" required class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                            <option value="">{{ __('tools-for-sale.select_option') }}</option>
                            <option value="new" @selected(old('condition') === 'new')>{{ __('tools-for-sale.conditions.new') }}</option>
                            <option value="used" @selected(old('condition') === 'used')>{{ __('tools-for-sale.conditions.used') }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.brand') }}</label>
                        <input type="text" name="brand" value="{{ old('brand') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                </div>
            </div>

            {{-- Step 2: Location --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('tools-for-sale.step_location') }}</h2>
                <script type="application/json" data-location-tree>{!! json_encode($locationTree, JSON_UNESCAPED_UNICODE) !!}</script>
                <div class="mt-5 space-y-3" data-location-group>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.country') }}</label>
                        <select data-location-country class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                            <option value="">{{ __('tools-for-sale.select_country') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.region') }}</label>
                        <select data-location-region required disabled class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300 disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="">{{ __('tools-for-sale.select_region') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.city') }}</label>
                        <select name="city_id" data-location-city data-old-value="{{ old('city_id') }}" required disabled class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300 disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="">{{ __('tools-for-sale.select_city') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Step 3: Listing details --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('tools-for-sale.step_details') }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.cover_photo') }}</label>
                        <div class="flex items-center gap-4">
                            <img data-photo-preview class="hidden h-20 w-20 rounded-xl object-cover" alt="">
                            <input type="file" name="cover_photo" accept="image/*" required data-photo-input class="block w-full text-sm text-warm-900 file:mr-4 file:rounded-full file:border-0 file:bg-warm-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                        </div>
                        <p class="mt-1 text-xs text-warm-900/50">{{ __('tools-for-sale.photo_upload_hint') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.price') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-warm-900/50"><x-currency-symbol /></span>
                            <input type="number" name="price" min="0" step="0.01" required value="{{ old('price') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 py-3 pl-8 pr-4 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                        </div>
                        <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-warm-800">
                            <input type="checkbox" name="price_negotiable" value="1" @checked(old('price_negotiable')) class="h-4 w-4 rounded border-warm-300 text-warm-900 focus:ring-warm-300">
                            <span class="font-semibold">{{ __('tools-for-sale.price_negotiable') }}</span>
                            <span class="text-xs text-warm-900/50">{{ __('tools-for-sale.price_negotiable_hint') }}</span>
                        </label>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.contact_number') }}</label>
                        <input type="tel" name="contact_number" required value="{{ old('contact_number') }}" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.description') }} (EN)</label>
                        <textarea name="description_en" rows="3" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">{{ old('description_en') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.description') }} (AR)</label>
                        <textarea name="description_ar" dir="rtl" rows="3" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">{{ old('description_ar') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Step 4: Review & verify --}}
            <div data-step class="hidden">
                <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('tools-for-sale.review_title') }}</h2>
                <dl class="mt-5 divide-y divide-warm-200 overflow-hidden rounded-2xl border border-warm-200 bg-white text-sm">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('tools-for-sale.name_en') }}</dt>
                        <dd data-review="name" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('tools-for-sale.city') }}</dt>
                        <dd data-review="city" class="font-semibold text-warm-900"></dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('tools-for-sale.price') }}</dt>
                        <dd class="flex items-center gap-1 font-semibold text-warm-900">
                            <x-currency-symbol />
                            <span data-review="price"></span>
                            <span data-review-negotiable class="hidden ms-1 rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-sky-700">{{ __('tools-for-sale.price_negotiable') }}</span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-warm-900/60">{{ __('tools-for-sale.contact_number') }}</dt>
                        <dd data-review="contact" class="font-semibold text-warm-900"></dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <label class="mb-1 block text-sm font-semibold text-warm-800">{{ __('tools-for-sale.captcha_label') }}</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <img
                            data-captcha-image
                            data-src="{{ route('tools-for-sale.captcha') }}"
                            src="{{ route('tools-for-sale.captcha') }}"
                            alt="{{ __('tools-for-sale.captcha_label') }}"
                            width="180" height="50"
                            class="h-[50px] w-[180px] rounded-lg border border-warm-200 bg-white"
                        >
                        <button type="button" data-captcha-refresh class="text-xs font-semibold text-warm-600 underline underline-offset-2">
                            {{ __('tools-for-sale.captcha_refresh') }}
                        </button>
                    </div>
                    <input
                        type="text" name="captcha" required autocomplete="off"
                        placeholder="{{ __('tools-for-sale.captcha_placeholder') }}"
                        class="mt-3 w-full max-w-xs rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300"
                    >
                </div>
            </div>

            <div class="sticky bottom-0 z-10 mt-8 flex items-center gap-3 border-t border-warm-200/70 bg-warm-50/95 py-4 backdrop-blur">
                <button type="button" data-back class="btn-warm-outline hidden">{{ __('tools-for-sale.back') }}</button>
                <div class="flex-1"></div>
                <button type="button" data-next class="btn-warm">{{ __('tools-for-sale.next') }}</button>
                <button type="submit" data-submit class="btn-warm hidden">{{ __('tools-for-sale.submit_post') }}</button>
            </div>
        </form>
    </div>
</x-layouts.site>
