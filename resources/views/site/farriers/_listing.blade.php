@php
    $sort = in_array(request('sort'), ['newest', 'price_asc', 'price_desc'], true)
        ? request('sort')
        : 'newest';

    $query = \App\Models\Farrier::query()
        ->visible()
        ->with(['city', 'country'])
        ->when(request()->filled('specialty'), fn ($q) => $q->where('specialty', 'like', '%' . request('specialty') . '%'))
        ->when(request('city_id'), fn ($q, $v) => $q->where('city_id', $v))
        ->when(request('country_id'), fn ($q, $v) => $q->where('country_id', $v))
        ->when(request()->filled('price_max'), fn ($q) => $q->where('price', '<=', (float) request('price_max')));

    match ($sort) {
        'price_asc' => $query->orderBy('price'),
        'price_desc' => $query->orderByDesc('price'),
        default => $query->latest('id'),
    };

    $farriers = $query->paginate(9)->withQueryString();

    $countries = \App\Models\Country::query()->public()->with('region.city')->orderBy('en_name')->get();

    $chips = [];

    if (request()->filled('specialty')) {
        $chips[] = ['label' => __('farriers.filter_specialty') . ': ' . request('specialty'), 'keys' => ['specialty']];
    }
    if ($cityId = request('city_id')) {
        if ($city = \App\Models\City::find($cityId)) {
            $chips[] = ['label' => __('farriers.filter_city') . ': ' . $city->name, 'keys' => ['city_id']];
        }
    }
    if ($countryId = request('country_id')) {
        if ($country = \App\Models\Country::find($countryId)) {
            $chips[] = ['label' => __('farriers.filter_country') . ': ' . $country->name, 'keys' => ['country_id']];
        }
    }
    if (request()->filled('price_max')) {
        $chips[] = ['label' => __('farriers.filter_price_max') . ': ' . \App\Models\SiteSetting::currency()['symbol'] . request('price_max'), 'keys' => ['price_max']];
    }

    $hasFilters = ! empty($chips);
@endphp

<div class="mx-auto max-w-7xl px-6 py-14" data-board>
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('farriers.nav_group') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ $heading ?? __('farriers.board_title') }}
        </h1>
        @if($subheading ?? null)
            <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
        @endif
        <a href="{{ route('farriers.create') }}" class="btn-warm mt-5 inline-flex">{{ __('farriers.post_a_farrier') }}</a>
    </div>

    @if(session('status'))
        <div class="mx-auto mt-8 max-w-xl rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-center text-sm font-semibold text-emerald-700" data-reveal>
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ request()->url() }}" data-board-filters class="mt-10">
        <div class="mt-3 flex justify-center gap-2 md:hidden">
            <button type="button" data-board-drawer-toggle class="btn-warm-outline !px-5 !py-2 text-sm">
                {{ __('farriers.filters_button') }}{{ $hasFilters ? ' · ' . count($chips) : '' }}
            </button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 rounded-3xl border border-warm-200/70 bg-white p-5 shadow-sm shadow-warm-900/5 md:grid-cols-4" data-board-panel>
            <div class="flex items-center justify-between md:hidden">
                <p class="font-display text-base font-semibold text-warm-900">{{ __('farriers.filters_button') }}</p>
                <button type="button" data-board-drawer-close class="text-sm font-semibold text-warm-600">{{ __('farriers.close') }}</button>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('farriers.filter_specialty') }}</label>
                <input type="text" name="specialty" value="{{ request('specialty') }}" placeholder="{{ __('farriers.any') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('farriers.filter_city') }}</label>
                <select name="city_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('farriers.any_city') }}</option>
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
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('farriers.filter_price_max') }}</label>
                <input type="number" min="0" name="price_max" value="{{ request('price_max') }}" placeholder="{{ __('farriers.any') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            <div class="flex items-end md:col-span-1 md:justify-end">
                <button type="submit" class="btn-warm w-full">{{ __('farriers.apply_filters') }}</button>
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
                {{ __('farriers.clear_filters') }}
            </a>
        </div>
    @endif

    <div class="mt-8 flex items-center justify-between text-sm text-warm-900/60">
        <span>{{ trans_choice('farriers.results_count', $farriers->total(), ['count' => $farriers->total()]) }}</span>

        <label class="flex items-center gap-2">
            <span class="hidden sm:inline">{{ __('farriers.sort_label') }}</span>
            <select form="board-sort" name="sort" onchange="document.getElementById('board-sort').submit()" class="rounded-lg border border-warm-200 bg-white px-3 py-1.5 text-sm font-semibold text-warm-800">
                <option value="newest" @selected($sort === 'newest')>{{ __('farriers.sort_newest') }}</option>
                <option value="price_asc" @selected($sort === 'price_asc')>{{ __('farriers.sort_price_asc') }}</option>
                <option value="price_desc" @selected($sort === 'price_desc')>{{ __('farriers.sort_price_desc') }}</option>
            </select>
        </label>
        <form id="board-sort" method="GET" action="{{ request()->url() }}" class="hidden">
            @foreach(request()->except(['sort', 'page']) as $key => $value)
                @if(is_array($value))
                    @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
        </form>
    </div>

    @if($farriers->isEmpty())
        <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('farriers.no_results_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('farriers.no_results_body') }}</p>
            <a href="{{ route('farriers.create') }}" class="btn-warm mt-6 inline-flex">{{ __('farriers.post_a_farrier') }}</a>
        </div>
    @else
        <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($farriers as $farrier)
                <article data-reveal-item class="card-warm overflow-hidden p-0">
                    <img src="{{ $farrier->cover_photo_url }}" alt="{{ $farrier->name }}" class="h-48 w-full object-cover">

                    <div class="p-5">
                        <div class="flex flex-wrap gap-1.5">
                            @if($farrier->specialty)
                                <span class="rounded-full bg-warm-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-700">{{ $farrier->specialty }}</span>
                            @endif
                            @if($farrier->years_experience !== null)
                                <span class="rounded-full bg-warm-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-700">{{ trans_choice('farriers.years_experience_badge', $farrier->years_experience, ['count' => $farrier->years_experience]) }}</span>
                            @endif
                        </div>

                        <p class="mt-3 font-display text-lg font-semibold text-warm-900">{{ $farrier->name }}</p>

                        <p class="mt-2 text-xs text-warm-900/60">📍 {{ $farrier->city?->name }}, {{ $farrier->country?->name }}</p>

                        <div class="mt-4 flex items-center justify-between border-t border-dashed border-warm-200 pt-4">
                            <x-currency-price :amount="$farrier->price" class="font-display text-sm font-semibold text-warm-900" />
                            <button type="button" data-reveal-contact="{{ $farrier->contact_number }}" class="js-reveal rounded-full bg-warm-900 px-4 py-1.5 text-xs font-bold text-white">
                                {{ __('farriers.contact_reveal') }}
                            </button>
                        </div>

                        <a href="{{ route('farriers.show', $farrier->id) }}" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('farriers.view_details') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $farriers->links() }}
        </div>
    @endif
</div>

<div data-board-drawer-backdrop class="fixed inset-0 z-40 hidden bg-warm-950/40 md:hidden"></div>
