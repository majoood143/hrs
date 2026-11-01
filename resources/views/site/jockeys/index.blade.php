@php
    $input = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300';
    $regionId = (int) ($filters['region_id'] ?? 0);
    $cityId = (int) ($filters['city_id'] ?? 0);
    $selectedRegion = $regionId ? $regions->firstWhere('id', $regionId) : null;
    $typeOptions = \App\Models\Jockey::typeOptions();

    $chips = array_values(array_filter([
        isset($filters['type']) ? ['label' => __('jockeys.filter_type').': '.$typeOptions[$filters['type']], 'keys' => ['type']] : null,
        $selectedRegion ? ['label' => __('jockeys.filter_region').': '.$selectedRegion->name, 'keys' => ['region_id', 'city_id']] : null,
        $filterCity ? ['label' => __('jockeys.filter_city').': '.$filterCity->name, 'keys' => ['city_id']] : null,
    ]));
@endphp

<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
>
    <div class="mx-auto max-w-7xl px-6 py-14">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('jockeys.nav_group') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('jockeys.board_title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('jockeys.board_subtitle') }}</p>
            <a href="{{ route('jockeys.create') }}" class="btn-warm mt-5 inline-flex">{{ __('jockeys.post_a_jockey') }}</a>
        </div>

        @if(session('status'))
            <div class="mx-auto mt-8 max-w-xl rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-center text-sm font-semibold text-emerald-700" data-reveal>
                {{ session('status') }}
            </div>
        @endif

        {{-- the boards' filter drawer (site.js transferBoardFilters): a panel on phones, a row on desktop --}}
        <form method="GET" action="{{ route('jockeys.index') }}" data-board-filters class="mt-10">
            <div class="flex justify-center md:hidden">
                <button type="button" data-board-drawer-toggle class="btn-warm-outline !px-5 !py-2 text-sm">
                    {{ __('farriers.filters_button') }}{{ $chips ? ' · '.count($chips) : '' }}
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 rounded-3xl border border-warm-200/70 bg-white p-5 shadow-sm shadow-warm-900/5 md:grid-cols-4" data-board-panel>
            <div class="flex items-center justify-between md:hidden">
                <p class="font-display text-base font-semibold text-warm-900">{{ __('farriers.filters_button') }}</p>
                <button type="button" data-board-drawer-close class="text-sm font-semibold text-warm-600">{{ __('farriers.close') }}</button>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('jockeys.filter_type') }}</label>
                <select name="type" data-auto-submit class="{{ $input }}">
                    <option value="">{{ __('jockeys.any_type') }}</option>
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('jockeys.filter_region') }}</label>
                <select name="region_id" data-auto-submit data-reset-on-change="city_id" class="{{ $input }}">
                    <option value="">{{ __('farriers.any_region') }}</option>
                    @foreach($countries as $country)
                        @continue($country->region->isEmpty())
                        <optgroup label="{{ $country->name }}">
                            @foreach($country->region as $region)
                                <option value="{{ $region->id }}" @selected($regionId === $region->id)>{{ $region->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('jockeys.filter_city') }}</label>
                <select name="city_id" data-auto-submit class="{{ $input }}">
                    <option value="">{{ __('farriers.any_city') }}</option>
                    @foreach($selectedRegion ? collect([$selectedRegion]) : $regions as $region)
                        @continue($region->city->isEmpty())
                        <optgroup label="{{ $region->name }}">
                            @foreach($region->city as $city)
                                <option value="{{ $city->id }}" @selected($cityId === $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="btn-warm w-full">{{ __('farriers.apply_filters') }}</button>
            </div>
            </div>
        </form>

        @if($chips)
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                @foreach($chips as $chip)
                    <a href="{{ request()->fullUrlWithQuery(array_merge(array_fill_keys($chip['keys'], null), ['page' => null])) }}"
                       class="inline-flex items-center gap-1.5 rounded-full border border-warm-200 bg-warm-100 px-3 py-1.5 text-xs font-semibold text-warm-800 hover:bg-warm-200">
                        {{ $chip['label'] }}
                        <span aria-hidden="true" class="text-warm-500">✕</span>
                    </a>
                @endforeach
                <a href="{{ route('jockeys.index') }}" class="rounded-full border border-dashed border-warm-300 px-3 py-1.5 text-xs font-semibold text-warm-700 hover:bg-warm-100">
                    {{ __('farriers.clear_filters') }}
                </a>
            </div>
        @endif

        <p class="mt-8 text-sm text-warm-900/60">{{ trans_choice('jockeys.results_count', $jockeys->total(), ['count' => $jockeys->total()]) }}</p>

        @if($jockeys->isEmpty())
            <div class="mt-6 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center" data-reveal>
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('jockeys.no_results_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('jockeys.no_results_body') }}</p>
                <a href="{{ route('jockeys.create') }}" class="btn-warm mt-6 inline-flex">{{ __('jockeys.post_a_jockey') }}</a>
            </div>
        @else
            <div data-reveal-group class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($jockeys as $jockey)
                    <article data-reveal-item class="card-warm overflow-hidden p-0">
                        @include('site.jockeys._photo', ['jockey' => $jockey, 'class' => 'h-48 w-full'])

                        <div class="p-5">
                            @include('site.jockeys._badges', ['jockey' => $jockey, 'size' => 'px-2.5 py-1 text-[11px]'])

                            <p class="mt-3 font-display text-lg font-semibold text-warm-900">{{ $jockey->name }}</p>
                            <p class="mt-2 text-xs text-warm-900/60">📍 {{ $jockey->city?->name }}, {{ $jockey->country?->name }}</p>

                            <div class="mt-4 flex items-center justify-end border-t border-dashed border-warm-200 pt-4">
                                <button type="button" data-reveal-contact="{{ $jockey->contact_number }}" class="js-reveal rounded-full bg-warm-900 px-4 py-1.5 text-xs font-bold text-white">
                                    {{ __('jockeys.contact_reveal') }}
                                </button>
                            </div>

                            <a href="{{ route('jockeys.show', $jockey->id) }}" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                                {{ __('jockeys.view_details') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">{{ $jockeys->links() }}</div>
        @endif
    </div>

    <div data-board-drawer-backdrop class="fixed inset-0 z-40 hidden bg-warm-950/40 md:hidden"></div>
</x-layouts.site>
