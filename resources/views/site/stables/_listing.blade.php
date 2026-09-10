@php
    $selectedServiceId = request()->filled('service_id') ? (int) request('service_id') : null;

    $sort = in_array(request('sort'), ['name', 'newest'], true) ? request('sort') : 'name';

    $query = \App\Models\Stable::query()
        ->active()
        ->with(['city', 'country', 'services'])
        ->when(request('city_id'), fn ($q, $v) => $q->where('city_id', $v))
        ->when(request('country_id'), fn ($q, $v) => $q->where('country_id', $v))
        ->when($selectedServiceId, fn ($q, $v) => $q->whereHas('services', fn ($q2) => $q2->where('stable_services.id', $v)));

    match ($sort) {
        'newest' => $query->latest('id'),
        default => $query->orderBy('en_name'),
    };

    $stables = $query->paginate(9)->withQueryString();

    $countries = \App\Models\Country::query()->public()->with('region.city')->orderBy('en_name')->get();
    $services = \App\Models\StableService::query()->orderBy('en_name')->get();

    $chips = [];

    if ($cityId = request('city_id')) {
        if ($city = \App\Models\City::find($cityId)) {
            $chips[] = ['label' => __('stables.filter_city') . ': ' . $city->name, 'keys' => ['city_id']];
        }
    }
    if ($countryId = request('country_id')) {
        if ($country = \App\Models\Country::find($countryId)) {
            $chips[] = ['label' => __('stables.filter_country') . ': ' . $country->name, 'keys' => ['country_id']];
        }
    }
    if ($selectedServiceId && $service = $services->firstWhere('id', $selectedServiceId)) {
        $chips[] = ['label' => __('stables.filter_service') . ': ' . $service->name, 'keys' => ['service_id']];
    }

    $hasFilters = ! empty($chips);
@endphp

<div class="mx-auto max-w-7xl px-6 py-14" data-board>
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('stables.nav_group') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ __('stables.board_title') }}
        </h1>
    </div>

    <form method="GET" action="{{ request()->url() }}" data-board-filters class="mt-10">
        <div class="mt-3 flex justify-center gap-2 md:hidden">
            <button type="button" data-board-drawer-toggle class="btn-warm-outline !px-5 !py-2 text-sm">
                {{ __('stables.filters_button') }}{{ $hasFilters ? ' · ' . count($chips) : '' }}
            </button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 rounded-3xl border border-warm-200/70 bg-white p-5 shadow-sm shadow-warm-900/5 md:grid-cols-4" data-board-panel>
            <div class="flex items-center justify-between md:hidden">
                <p class="font-display text-base font-semibold text-warm-900">{{ __('stables.filters_button') }}</p>
                <button type="button" data-board-drawer-close class="text-sm font-semibold text-warm-600">{{ __('stables.close') }}</button>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('stables.filter_city') }}</label>
                <select name="city_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('stables.any_city') }}</option>
                    @foreach($countries as $country)
                        @php($cities = $country->region->flatMap->city)
                        @continue($cities->isEmpty())
                        <optgroup label="{{ $country->name }}">
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" @selected(request('city_id') == $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('stables.filter_service') }}</label>
                <select name="service_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('stables.any') }}</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($selectedServiceId === $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end md:col-span-2 md:justify-end">
                <button type="submit" class="btn-warm w-full md:w-auto">{{ __('stables.apply_filters') }}</button>
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
                {{ __('stables.clear_filters') }}
            </a>
        </div>
    @endif

    <div class="mt-8 flex items-center justify-between text-sm text-warm-900/60">
        <span>{{ trans_choice('stables.results_count', $stables->total(), ['count' => $stables->total()]) }}</span>

        <label class="flex items-center gap-2">
            <span class="hidden sm:inline">{{ __('stables.sort_label') }}</span>
            <select form="board-sort" name="sort" onchange="document.getElementById('board-sort').submit()" class="rounded-lg border border-warm-200 bg-white px-3 py-1.5 text-sm font-semibold text-warm-800">
                <option value="name" @selected($sort === 'name')>{{ __('stables.sort_name') }}</option>
                <option value="newest" @selected($sort === 'newest')>{{ __('stables.sort_newest') }}</option>
            </select>
        </label>
        <form id="board-sort" method="GET" action="{{ request()->url() }}" class="hidden">
            @foreach(request()->except(['sort', 'page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>
    </div>

    @if($stables->isEmpty())
        <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('stables.no_results_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('stables.no_results_body') }}</p>
        </div>
    @else
        <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($stables as $stable)
                <article data-reveal-item class="card-warm overflow-hidden p-0">
                    @if($stable->cover_photo_url)
                        <img src="{{ $stable->cover_photo_url }}" alt="{{ $stable->name }}" class="h-48 w-full object-cover">
                    @endif

                    <div class="p-5">
                        <p class="font-display text-lg font-semibold text-warm-900">{{ $stable->name }}</p>
                        <p class="mt-1 text-xs text-warm-900/60">📍 {{ $stable->city?->name }}, {{ $stable->country?->name }}</p>

                        @if($stable->services->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($stable->services as $service)
                                    <span class="rounded-full bg-warm-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        <a href="{{ route('stables.show', $stable->slug) }}" class="btn-warm mt-4 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('stables.view_stable') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $stables->links() }}
        </div>
    @endif
</div>

<div data-board-drawer-backdrop class="fixed inset-0 z-40 hidden bg-warm-950/40 md:hidden"></div>
