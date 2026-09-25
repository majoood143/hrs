@php
    $selectedType = in_array(request('type'), array_keys(\App\Models\Center::TYPES), true) ? request('type') : null;
    $selectedServiceId = request()->filled('service_id') ? (int) request('service_id') : null;
    $selectedCountryId = request()->filled('country_id') ? (int) request('country_id') : null;
    $selectedRegionId = request()->filled('region_id') ? (int) request('region_id') : null;
    $selectedCityId = request()->filled('city_id') ? (int) request('city_id') : null;

    $sort = in_array(request('sort'), ['name', 'newest'], true) ? request('sort') : 'name';

    $query = \App\Models\Center::query()
        ->active()
        ->ofType($selectedType)
        ->with(['city', 'country', 'services'])
        ->when($selectedCountryId, fn ($q, $v) => $q->where('country_id', $v))
        ->when($selectedRegionId, fn ($q, $v) => $q->where('region_id', $v))
        ->when($selectedCityId, fn ($q, $v) => $q->where('city_id', $v))
        ->when($selectedServiceId, fn ($q, $v) => $q->whereHas('services', fn ($q2) => $q2->where('center_services.id', $v)));

    match ($sort) {
        'newest' => $query->latest('id'),
        default => $query->orderBy('en_name'),
    };

    $centers = $query->paginate(9)->withQueryString();

    $countries = \App\Models\Country::query()->public()->with('region.city')->orderBy('en_name')->get();
    $allRegions = $countries->flatMap->region;

    $regions = $selectedCountryId
        ? ($countries->firstWhere('id', $selectedCountryId)?->region ?? collect())
        : $allRegions;

    $groupCitiesByCountry = ! $selectedCountryId && ! $selectedRegionId;
    $groupCitiesByRegion = $selectedCountryId && ! $selectedRegionId;

    if ($selectedRegionId) {
        $cities = $allRegions->firstWhere('id', $selectedRegionId)?->city ?? collect();
    } elseif ($selectedCountryId) {
        $cities = $regions->flatMap->city;
    } else {
        $cities = null;
    }

    $services = \App\Models\CenterService::query()->forType($selectedType)->orderBy('en_name')->get();

    $chips = [];

    if ($selectedType) {
        $chips[] = ['label' => __('centers.filter_type') . ': ' . __('centers.types.' . $selectedType), 'keys' => ['type']];
    }
    if ($selectedCountryId && $country = \App\Models\Country::find($selectedCountryId)) {
        $chips[] = ['label' => __('centers.filter_country') . ': ' . $country->name, 'keys' => ['country_id', 'region_id', 'city_id']];
    }
    if ($selectedRegionId && $region = $allRegions->firstWhere('id', $selectedRegionId)) {
        $chips[] = ['label' => __('centers.filter_region') . ': ' . $region->name, 'keys' => ['region_id', 'city_id']];
    }
    if ($selectedCityId && $city = \App\Models\City::find($selectedCityId)) {
        $chips[] = ['label' => __('centers.filter_city') . ': ' . $city->name, 'keys' => ['city_id']];
    }
    if ($selectedServiceId && $service = $services->firstWhere('id', $selectedServiceId)) {
        $chips[] = ['label' => __('centers.filter_service') . ': ' . $service->name, 'keys' => ['service_id']];
    }

    $hasFilters = ! empty($chips);
@endphp

<div class="mx-auto max-w-7xl px-6 py-14" data-board>
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('centers.nav_group') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ __('centers.board_title') }}
        </h1>
    </div>

    <form method="GET" action="{{ request()->url() }}" data-board-filters class="mt-10">
        <div class="mt-3 flex justify-center gap-2 md:hidden">
            <button type="button" data-board-drawer-toggle class="btn-warm-outline !px-5 !py-2 text-sm">
                {{ __('centers.filters_button') }}{{ $hasFilters ? ' · ' . count($chips) : '' }}
            </button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 rounded-3xl border border-warm-200/70 bg-white p-5 shadow-sm shadow-warm-900/5 sm:grid-cols-2 lg:grid-cols-6" data-board-panel>
            <div class="flex items-center justify-between md:hidden">
                <p class="font-display text-base font-semibold text-warm-900">{{ __('centers.filters_button') }}</p>
                <button type="button" data-board-drawer-close class="text-sm font-semibold text-warm-600">{{ __('centers.close') }}</button>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('centers.filter_type') }}</label>
                <select name="type" data-auto-submit data-reset-on-change="service_id" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('centers.any_type') }}</option>
                    @foreach(\App\Models\Center::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($selectedType === $value)>{{ __('centers.types.' . $value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('centers.filter_country') }}</label>
                <select name="country_id" data-auto-submit data-reset-on-change="region_id,city_id" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('centers.any_country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" @selected($selectedCountryId === $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('centers.filter_region') }}</label>
                <select name="region_id" data-auto-submit data-reset-on-change="city_id" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('centers.any_region') }}</option>
                    @foreach($regions as $region)
                        <option value="{{ $region->id }}" @selected($selectedRegionId === $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('centers.filter_city') }}</label>
                <select name="city_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('centers.any_city') }}</option>
                    @if($groupCitiesByCountry)
                        @foreach($countries as $country)
                            @php($countryCities = $country->region->flatMap->city)
                            @continue($countryCities->isEmpty())
                            <optgroup label="{{ $country->name }}">
                                @foreach($countryCities as $city)
                                    <option value="{{ $city->id }}" @selected($selectedCityId === $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @elseif($groupCitiesByRegion)
                        @foreach($regions as $region)
                            @continue($region->city->isEmpty())
                            <optgroup label="{{ $region->name }}">
                                @foreach($region->city as $city)
                                    <option value="{{ $city->id }}" @selected($selectedCityId === $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @else
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" @selected($selectedCityId === $city->id)>{{ $city->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('centers.filter_service') }}</label>
                <select name="service_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('centers.any') }}</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($selectedServiceId === $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="btn-warm w-full">{{ __('centers.apply_filters') }}</button>
            </div>
        </div>
    </form>

    @if($hasFilters)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            @foreach($chips as $chip)
                <a href="{{ request()->fullUrlWithQuery(array_merge(array_fill_keys($chip['keys'], null), ['page' => null])) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border border-warm-200 bg-warm-100 px-3 py-1.5 text-xs font-semibold text-warm-800 hover:bg-warm-200">
                    {{ $chip['label'] }}
                    <span aria-hidden="true" class="text-warm-500">✕</span>
                </a>
            @endforeach
            <a href="{{ request()->url() }}" class="rounded-full border border-dashed border-warm-300 px-3 py-1.5 text-xs font-semibold text-warm-700 hover:bg-warm-100">
                {{ __('centers.clear_filters') }}
            </a>
        </div>
    @endif

    <div class="mt-8 flex items-center justify-between text-sm text-warm-900/60">
        <span>{{ trans_choice('centers.results_count', $centers->total(), ['count' => $centers->total()]) }}</span>

        <label class="flex items-center gap-2">
            <span class="hidden sm:inline">{{ __('centers.sort_label') }}</span>
            <select form="board-sort" name="sort" onchange="document.getElementById('board-sort').submit()" class="rounded-lg border border-warm-200 bg-white px-3 py-1.5 text-sm font-semibold text-warm-800">
                <option value="name" @selected($sort === 'name')>{{ __('centers.sort_name') }}</option>
                <option value="newest" @selected($sort === 'newest')>{{ __('centers.sort_newest') }}</option>
            </select>
        </label>
        <form id="board-sort" method="GET" action="{{ request()->url() }}" class="hidden">
            @foreach(request()->except(['sort', 'page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>
    </div>

    @if($centers->isEmpty())
        <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('centers.no_results_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('centers.no_results_body') }}</p>
        </div>
    @else
        <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($centers as $center)
                <article data-reveal-item class="card-warm overflow-hidden p-0">
                    @if($center->cover_photo_url)
                        <x-zoomable-image :src="$center->cover_photo_url" alt="{{ $center->name }}" class="h-48 w-full" />
                    @endif

                    <div class="p-5">
                        <span class="rounded-full bg-warm-900/5 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-600">{{ $center->type_label }}</span>
                        <p class="mt-2 font-display text-lg font-semibold text-warm-900">{{ $center->name }}</p>
                        <p class="mt-1 text-xs text-warm-900/60">📍 {{ $center->city?->name }}, {{ $center->country?->name }}</p>

                        @if($center->services->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($center->services as $service)
                                    <span class="rounded-full bg-warm-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        <a href="{{ route('centers.show', $center->slug) }}" class="btn-warm mt-4 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('centers.view_center') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $centers->links() }}
        </div>
    @endif
</div>

<div data-board-drawer-backdrop class="fixed inset-0 z-40 hidden bg-warm-950/40 md:hidden"></div>
