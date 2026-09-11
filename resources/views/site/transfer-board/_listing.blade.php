@php
    $selectedType = in_array(request('type'), ['offer', 'request'], true) ? request('type') : null;

    $sort = in_array(request('sort'), ['soonest', 'newest', 'price_asc', 'price_desc', 'capacity'], true)
        ? request('sort')
        : 'soonest';

    $query = \App\Models\TransferPost::query()
        ->visible()
        ->with(['fromCity', 'fromCountry', 'toCity', 'toCountry'])
        ->when($selectedType, fn ($q, $type) => $q->where('type', $type))
        ->when(request('from_city_id'), fn ($q, $v) => $q->where('from_city_id', $v))
        ->when(request('to_city_id'), fn ($q, $v) => $q->where('to_city_id', $v))
        ->when(request('from_country_id'), fn ($q, $v) => $q->where('from_country_id', $v))
        ->when(request('to_country_id'), fn ($q, $v) => $q->where('to_country_id', $v))
        ->when(request()->filled('date_from'), fn ($q) => $q->whereDate('transfer_date', '>=', request('date_from')))
        ->when(request()->filled('date_to'), fn ($q) => $q->whereDate('transfer_date', '<=', request('date_to')))
        ->when(request('capacity_min'), fn ($q, $v) => $q->where('capacity', '>=', (int) $v))
        ->when(
            request()->filled('price_max') && $selectedType !== 'request',
            fn ($q) => $q->where('price', '<=', (float) request('price_max'))
        );

    match ($sort) {
        'newest' => $query->latest('id'),
        'price_asc' => $query->orderByRaw('price is null, price asc'),
        'price_desc' => $query->orderByRaw('price is null, price desc'),
        'capacity' => $query->orderByDesc('capacity'),
        default => $query->orderBy('transfer_date'),
    };

    $posts = $query->paginate(9)->withQueryString();

    $countries = \App\Models\Country::query()->public()->with('region.city')->orderBy('en_name')->get();

    $chips = [];

    if ($cityId = request('from_city_id')) {
        if ($city = \App\Models\City::find($cityId)) {
            $chips[] = ['label' => __('transportation.filter_from') . ': ' . $city->name, 'keys' => ['from_city_id']];
        }
    }
    if ($cityId = request('to_city_id')) {
        if ($city = \App\Models\City::find($cityId)) {
            $chips[] = ['label' => __('transportation.filter_to') . ': ' . $city->name, 'keys' => ['to_city_id']];
        }
    }
    if ($countryId = request('from_country_id')) {
        if ($country = \App\Models\Country::find($countryId)) {
            $chips[] = ['label' => __('transportation.filter_from') . ': ' . $country->name, 'keys' => ['from_country_id']];
        }
    }
    if ($countryId = request('to_country_id')) {
        if ($country = \App\Models\Country::find($countryId)) {
            $chips[] = ['label' => __('transportation.filter_to') . ': ' . $country->name, 'keys' => ['to_country_id']];
        }
    }
    if (request()->filled('date_from') || request()->filled('date_to')) {
        $chips[] = [
            'label' => __('transportation.filter_date') . ': ' . (request('date_from') ?: '…') . ' – ' . (request('date_to') ?: '…'),
            'keys' => ['date_from', 'date_to'],
        ];
    }
    if (request()->filled('capacity_min')) {
        $chips[] = ['label' => __('transportation.filter_capacity_min_chip', ['count' => request('capacity_min')]), 'keys' => ['capacity_min']];
    }
    if (request()->filled('price_max') && $selectedType !== 'request') {
        $chips[] = ['label' => __('transportation.filter_price_max') . ': ' . \App\Models\SiteSetting::currency()['symbol'] . request('price_max'), 'keys' => ['price_max']];
    }

    $hasFilters = ! empty($chips);
@endphp

<div class="mx-auto max-w-7xl px-6 py-14" data-board>
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="section-eyebrow">{{ __('transportation.nav_group') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
            {{ $heading ?? __('transportation.board_title') }}
        </h1>
        @if($subheading ?? null)
            <p class="mt-3 text-warm-900/70">{{ $subheading }}</p>
        @endif
        <a href="{{ route('transfer-board.create') }}" class="btn-warm mt-5 inline-flex">{{ __('transportation.post_a_transfer') }}</a>
    </div>

    @if(session('status'))
        <div class="mx-auto mt-8 max-w-xl rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-center text-sm font-semibold text-emerald-700" data-reveal>
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ request()->url() }}" data-board-filters class="mt-10">
        <div class="flex flex-wrap items-center justify-center gap-2">
            <label class="cursor-pointer">
                <input type="radio" name="type" value="" class="sr-only" data-type-toggle {{ ! $selectedType ? 'checked' : '' }}>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-transparent px-5 py-2 text-sm font-semibold {{ ! $selectedType ? 'bg-warm-900 text-white' : 'bg-warm-100 text-warm-700' }}">
                    {{ __('transportation.type_all') }}
                </span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="type" value="offer" class="sr-only" data-type-toggle {{ $selectedType === 'offer' ? 'checked' : '' }}>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 px-5 py-2 text-sm font-semibold {{ $selectedType === 'offer' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ __('transportation.type_offer') }}
                </span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="type" value="request" class="sr-only" data-type-toggle {{ $selectedType === 'request' ? 'checked' : '' }}>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 px-5 py-2 text-sm font-semibold {{ $selectedType === 'request' ? 'bg-blue-600 text-white border-blue-600' : 'bg-blue-50 text-blue-700' }}">
                    {{ __('transportation.type_request') }}
                </span>
            </label>
        </div>

        <div class="mt-3 flex justify-center gap-2 md:hidden">
            <button type="button" data-board-drawer-toggle class="btn-warm-outline !px-5 !py-2 text-sm">
                {{ __('transportation.filters_button') }}{{ $hasFilters ? ' · ' . count($chips) : '' }}
            </button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 rounded-3xl border border-warm-200/70 bg-white p-5 shadow-sm shadow-warm-900/5 md:grid-cols-6" data-board-panel>
            <div class="flex items-center justify-between md:hidden">
                <p class="font-display text-base font-semibold text-warm-900">{{ __('transportation.filters_button') }}</p>
                <button type="button" data-board-drawer-close class="text-sm font-semibold text-warm-600">{{ __('transportation.close') }}</button>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_from') }}</label>
                <select name="from_city_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('transportation.any_city') }}</option>
                    @foreach($countries as $country)
                        @php($cities = $country->region->flatMap->city)
                        @continue($cities->isEmpty())
                        <optgroup label="{{ $country->name }}">
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" @selected(request('from_city_id') == $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_to') }}</label>
                <select name="to_city_id" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('transportation.any_city') }}</option>
                    @foreach($countries as $country)
                        @php($cities = $country->region->flatMap->city)
                        @continue($cities->isEmpty())
                        <optgroup label="{{ $country->name }}">
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" @selected(request('to_city_id') == $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_date_from') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_date_to') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_capacity_min') }}</label>
                <input type="number" min="1" name="capacity_min" value="{{ request('capacity_min') }}" placeholder="{{ __('transportation.any') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            @if($selectedType !== 'request')
                <div class="md:col-span-1">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.filter_price_max') }}</label>
                    <input type="number" min="0" name="price_max" value="{{ request('price_max') }}" placeholder="{{ __('transportation.any') }}" data-auto-submit class="w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                </div>
            @endif

            <div class="flex items-end md:col-span-6 md:justify-end">
                <button type="submit" class="btn-warm w-full md:w-auto">{{ __('transportation.apply_filters') }}</button>
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
                {{ __('transportation.clear_filters') }}
            </a>
        </div>
    @endif

    <div class="mt-8 flex items-center justify-between text-sm text-warm-900/60">
        <span>{{ trans_choice('transportation.results_count', $posts->total(), ['count' => $posts->total()]) }}</span>

        <label class="flex items-center gap-2">
            <span class="hidden sm:inline">{{ __('transportation.sort_label') }}</span>
            <select form="board-sort" name="sort" onchange="document.getElementById('board-sort').submit()" class="rounded-lg border border-warm-200 bg-white px-3 py-1.5 text-sm font-semibold text-warm-800">
                <option value="soonest" @selected($sort === 'soonest')>{{ __('transportation.sort_soonest') }}</option>
                <option value="newest" @selected($sort === 'newest')>{{ __('transportation.sort_newest') }}</option>
                <option value="price_asc" @selected($sort === 'price_asc')>{{ __('transportation.sort_price_asc') }}</option>
                <option value="price_desc" @selected($sort === 'price_desc')>{{ __('transportation.sort_price_desc') }}</option>
                <option value="capacity" @selected($sort === 'capacity')>{{ __('transportation.sort_capacity') }}</option>
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

    @if($posts->isEmpty())
        <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
            <p class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.no_results_title') }}</p>
            <p class="mt-2 text-warm-900/60">{{ __('transportation.no_results_body') }}</p>
            <a href="{{ route('transfer-board.create') }}" class="btn-warm mt-6 inline-flex">{{ __('transportation.post_a_transfer') }}</a>
        </div>
    @else
        <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <article data-reveal-item class="card-warm overflow-hidden {{ $post->cover_photo_url ? 'p-0' : 'p-5' }}">
                    @if($post->cover_photo_url)
                        <img src="{{ $post->cover_photo_url }}" alt="" class="h-40 w-full object-cover">
                    @endif

                    <div @class(['p-5' => $post->cover_photo_url])>
                        <span class="inline-block rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide {{ $post->type === 'offer' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $post->type === 'offer' ? __('transportation.type_offer') : __('transportation.type_request') }}
                        </span>

                        <p class="mt-3 font-display text-lg font-semibold text-warm-900">
                            {{ $post->fromCity?->name }}, {{ $post->fromCountry?->name }}
                            <span aria-hidden="true">→</span>
                            {{ $post->toCity?->name }}, {{ $post->toCountry?->name }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-warm-900/60">
                            <span>📅 {{ $post->transfer_date->format('M d, Y') }}</span>
                            <span>🐎 {{ trans_choice('transportation.spaces_available', $post->capacity, ['count' => $post->capacity]) }}</span>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-dashed border-warm-200 pt-4">
                            @if($post->type === 'offer' && filled($post->price))
                                <x-currency-price :amount="$post->price" class="font-display text-sm font-semibold text-warm-900" />
                            @else
                                <span class="font-display text-sm font-semibold text-warm-900">{{ __('transportation.budget_open') }}</span>
                            @endif
                            <button type="button" data-reveal-contact="{{ $post->contact_number }}" class="js-reveal rounded-full bg-warm-900 px-4 py-1.5 text-xs font-bold text-white">
                                {{ __('transportation.contact_reveal') }}
                            </button>
                        </div>

                        <a href="{{ route('transfer-board.show', $post->id) }}" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('transportation.view_details') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $posts->links() }}
        </div>
    @endif
</div>

<div data-board-drawer-backdrop class="fixed inset-0 z-40 hidden bg-warm-950/40 md:hidden"></div>
