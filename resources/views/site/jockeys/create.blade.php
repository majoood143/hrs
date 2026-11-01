@php
    $input = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 placeholder:text-warm-900/40 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300 disabled:cursor-not-allowed disabled:opacity-50';
    $label = 'mb-1 block text-sm font-semibold text-warm-800';
    $hint = 'mt-1 text-xs text-warm-900/50';
@endphp

<x-layouts.site :seo-title="$seoTitle">
    <div class="mx-auto max-w-3xl px-6 py-14 pb-28">
        <div class="text-center" data-reveal>
            <p class="section-eyebrow">{{ __('jockeys.nav_group') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('jockeys.post_page_title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('jockeys.post_page_subtitle') }}</p>
        </div>

        @if($errors->any())
            <div class="mt-8 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-semibold">{{ __('farriers.form_errors_title') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('jockeys.store') }}" class="mt-8 space-y-8" enctype="multipart/form-data" data-location-form>
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}" for="en_name">{{ __('jockeys.name_en') }}</label>
                    <input id="en_name" type="text" name="en_name" dir="ltr" required maxlength="255" value="{{ old('en_name') }}" placeholder="{{ __('jockeys.name_en_placeholder') }}" aria-describedby="en_name_hint" class="{{ $input }}">
                    <p id="en_name_hint" class="{{ $hint }}">{{ __('jockeys.name_en_hint') }}</p>
                </div>
                <div>
                    <label class="{{ $label }}" for="ar_name">{{ __('jockeys.name_ar') }}</label>
                    <input id="ar_name" type="text" name="ar_name" dir="rtl" required maxlength="255" value="{{ old('ar_name') }}" placeholder="{{ __('jockeys.name_ar_placeholder') }}" aria-describedby="ar_name_hint" class="{{ $input }}">
                    <p id="ar_name_hint" class="{{ $hint }}">{{ __('jockeys.name_ar_hint') }}</p>
                </div>
            </div>

            <fieldset aria-describedby="types_hint">
                <legend class="{{ $label }}">{{ __('jockeys.types_label') }}</legend>
                <p id="types_hint" class="{{ $hint }}">{{ __('jockeys.types_hint') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach(\App\Models\Jockey::typeOptions() as $value => $text)
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-warm-200 bg-warm-50 px-4 py-2 text-sm text-warm-900 has-checked:border-warm-700 has-checked:bg-warm-100">
                            <input type="checkbox" name="types[]" value="{{ $value }}" @checked(in_array($value, (array) old('types', []), true)) class="rounded border-warm-300 text-warm-700 focus:ring-warm-300">
                            {{ $text }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- country → region → city (site.js initLocationCascades); only city_id is sent --}}
            <fieldset aria-describedby="location_hint">
                <legend class="{{ $label }}">{{ __('jockeys.tabs.location') }}</legend>
                <p id="location_hint" class="{{ $hint }}">{{ __('jockeys.location_hint') }}</p>
                <script type="application/json" data-location-tree>{!! json_encode($locationTree, JSON_UNESCAPED_UNICODE) !!}</script>
                <div class="mt-3 grid gap-5 sm:grid-cols-3" data-location-group>
                    <div>
                        <label class="{{ $label }}" for="country_id">{{ __('jockeys.country') }}</label>
                        <select id="country_id" data-location-country required class="{{ $input }}">
                            <option value="">{{ __('jockeys.select_country') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="region_id">{{ __('jockeys.region') }}</label>
                        <select id="region_id" data-location-region required disabled class="{{ $input }}">
                            <option value="">{{ __('jockeys.select_region') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="city_id">{{ __('jockeys.city') }}</label>
                        <select id="city_id" name="city_id" data-location-city data-old-value="{{ old('city_id') }}" required disabled class="{{ $input }}">
                            <option value="">{{ __('jockeys.select_city') }}</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}" for="years_experience">{{ __('jockeys.years_experience') }}</label>
                    <input id="years_experience" type="number" name="years_experience" min="0" max="80" value="{{ old('years_experience') }}" placeholder="{{ __('jockeys.years_experience_placeholder') }}" aria-describedby="years_experience_hint" class="{{ $input }}">
                    <p id="years_experience_hint" class="{{ $hint }}">{{ __('jockeys.years_experience_hint') }}</p>
                </div>
                <div>
                    <label class="{{ $label }}" for="contact_number">{{ __('jockeys.contact_number') }}</label>
                    <input id="contact_number" type="tel" name="contact_number" dir="ltr" required maxlength="30" value="{{ old('contact_number') }}" placeholder="{{ __('jockeys.contact_number_placeholder') }}" aria-describedby="contact_number_hint" class="{{ $input }}">
                    <p id="contact_number_hint" class="{{ $hint }}">{{ __('jockeys.contact_number_hint') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $label }}" for="cover_photo">{{ __('jockeys.cover_photo') }}</label>
                    <input id="cover_photo" type="file" name="cover_photo" accept="image/*" aria-describedby="cover_photo_hint" class="block w-full text-sm text-warm-900 file:me-4 file:rounded-full file:border-0 file:bg-warm-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                    <p id="cover_photo_hint" class="{{ $hint }}">{{ __('jockeys.photo_upload_hint') }}</p>
                </div>
            </div>

            <div class="grid gap-5">
                <div>
                    <label class="{{ $label }}" for="description_en">{{ __('jockeys.description_en') }}</label>
                    <textarea id="description_en" name="description_en" dir="ltr" rows="3" placeholder="{{ __('jockeys.description_en_placeholder') }}" aria-describedby="description_hint" class="{{ $input }}">{{ old('description_en') }}</textarea>
                </div>
                <div>
                    <label class="{{ $label }}" for="description_ar">{{ __('jockeys.description_ar') }}</label>
                    <textarea id="description_ar" name="description_ar" dir="rtl" rows="3" placeholder="{{ __('jockeys.description_ar_placeholder') }}" aria-describedby="description_hint" class="{{ $input }}">{{ old('description_ar') }}</textarea>
                    <p id="description_hint" class="{{ $hint }}">{{ __('jockeys.description_hint') }}</p>
                </div>
            </div>

            <div>
                <label class="{{ $label }}" for="captcha">{{ __('farriers.captcha_label') }}</label>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- the farriers' captcha image: same session code, any form can check it --}}
                    <img id="jockey-captcha" src="{{ route('farriers.captcha') }}" alt="{{ __('farriers.captcha_label') }}" width="180" height="50" class="h-[50px] w-[180px] rounded-lg border border-warm-200 bg-white">
                    <button type="button" onclick="document.getElementById('jockey-captcha').src = '{{ route('farriers.captcha') }}?' + Date.now()" class="text-xs font-semibold text-warm-600 underline underline-offset-2">
                        {{ __('farriers.captcha_refresh') }}
                    </button>
                </div>
                <input id="captcha" type="text" name="captcha" dir="ltr" required autocomplete="off" placeholder="{{ __('farriers.captcha_placeholder') }}" aria-describedby="captcha_hint" class="mt-3 max-w-xs {{ $input }}">
                <p id="captcha_hint" class="{{ $hint }}">{{ __('jockeys.captcha_hint') }}</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-warm">{{ __('farriers.submit_post') }}</button>
            </div>
        </form>
    </div>
</x-layouts.site>
