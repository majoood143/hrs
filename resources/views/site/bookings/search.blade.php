@php
    $query = fn (array $changes = []) => array_filter(array_merge([
        'date' => $date,
        'riders' => $riders,
        'region_id' => $filters['region_id'],
        'city_id' => $filters['city_id'],
        'type' => $filters['type'],
        'time' => $filters['time'],
    ], $changes), fn ($value) => $value !== null && $value !== '');
    $field = 'mt-1 w-full rounded-xl border border-warm-200 bg-white px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none';
    $selectedDate = \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale());
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? __('stable_bookings.search.intro')" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-6xl px-6 py-14">
        <p class="section-eyebrow">{{ __('stable_bookings.search.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('stable_bookings.search.title') }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-warm-700">{{ __('stable_bookings.search.intro') }}</p>

        @if(session('error'))
            <div class="mt-6 flex gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <form method="GET" action="{{ route('bookings.search') }}" class="card-warm mt-8 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-1">
                <label for="date" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-calendar-days class="h-4 w-4" aria-hidden="true" />{{ __('stable_bookings.search.date') }}
                </label>
                <input id="date" name="date" type="date" value="{{ $date }}" min="{{ $today }}" class="{{ $field }}" dir="ltr">
            </div>
            <div class="lg:col-span-1">
                <label for="riders" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-user-group class="h-4 w-4" aria-hidden="true" />{{ __('stable_bookings.search.riders') }}
                </label>
                <select id="riders" name="riders" class="{{ $field }}">
                    @foreach(range(1, 10) as $n)
                        <option value="{{ $n }}" @selected($riders === $n)>{{ trans_choice('stable_bookings.riders_count', $n, ['count' => $n]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1">
                <label for="region_id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-map class="h-4 w-4" aria-hidden="true" />{{ __('stables.filter_region') }}
                </label>
                <select id="region_id" name="region_id" class="{{ $field }}">
                    <option value="">{{ __('stables.any_region') }}</option>
                    @foreach($regions as $region)
                        <option value="{{ $region->id }}" @selected($filters['region_id'] === $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1">
                <label for="city_id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-map-pin class="h-4 w-4" aria-hidden="true" />{{ __('stables.filter_city') }}
                </label>
                <select id="city_id" name="city_id" class="{{ $field }}">
                    <option value="">{{ __('stables.any_city') }}</option>
                    @foreach($regions as $region)
                        @if($region->city->isNotEmpty())
                            <optgroup label="{{ $region->name }}">
                                @foreach($region->city as $city)
                                    <option value="{{ $city->id }}" @selected($filters['city_id'] === $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1">
                <label for="time" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warm-700">
                    <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" />{{ __('stable_bookings.search.time') }}
                </label>
                <select id="time" name="time" class="{{ $field }}">
                    <option value="">{{ __('stable_bookings.search.any_time') }}</option>
                    @foreach(\App\Services\Stables\SlotFinder::TIMES_OF_DAY as $time)
                        <option value="{{ $time }}" @selected($filters['time'] === $time)>{{ __('stable_bookings.search.times.'.$time) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end lg:col-span-1">
                @if(count($types) > 1)
                    <input type="hidden" name="type" value="{{ $filters['type'] }}">
                @endif
                <button type="submit" class="btn-warm w-full justify-center">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />
                    {{ __('stable_bookings.search.button') }}
                </button>
            </div>
        </form>

        {{-- the next seven days, with how many times each has --}}
        <nav class="mt-6 flex gap-2 overflow-x-auto pb-2" aria-label="{{ __('stable_bookings.search.week') }}">
            @foreach($week as $day)
                @php $d = \Carbon\CarbonImmutable::parse($day['date'])->locale(app()->getLocale()); @endphp
                <a href="{{ route('bookings.search', $query(['date' => $day['date']])) }}"
                   @class([
                       'flex min-w-20 shrink-0 flex-col items-center rounded-2xl border px-3 py-2 text-center transition',
                       'border-warm-600 bg-warm-600 text-white' => $day['date'] === $date,
                       'border-warm-200 bg-white text-warm-900 hover:border-warm-400' => $day['date'] !== $date,
                   ])
                   @if($day['date'] === $date) aria-current="date" @endif>
                    <span class="text-xs font-semibold uppercase">{{ $d->translatedFormat('D') }}</span>
                    <span class="font-display text-xl font-semibold">{{ $d->format('j') }}</span>
                    <span @class(['text-[11px]', 'text-white/80' => $day['date'] === $date, 'text-warm-600' => $day['date'] !== $date])>
                        {{ trans_choice('stable_bookings.search.times_count', $day['count'], ['count' => $day['count']]) }}
                    </span>
                </a>
            @endforeach
        </nav>

        <h2 class="mt-8 flex items-center gap-2 font-display text-xl font-semibold text-warm-900">
            <x-heroicon-o-calendar class="h-6 w-6 text-warm-600" aria-hidden="true" />
            {{ $selectedDate->translatedFormat('l j F Y') }}
        </h2>

        @if($results->isEmpty())
            <div class="card-warm mt-4 flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-warm-50 text-warm-600 ring-4 ring-warm-100">
                    <x-heroicon-o-calendar-days class="h-7 w-7" aria-hidden="true" />
                </span>
                <p class="font-display text-lg font-semibold text-warm-900">{{ __('stable_bookings.search.none_title') }}</p>
                <p class="text-sm text-warm-700">{{ __('stable_bookings.search.none_body') }}</p>
                @if($nextDate)
                    <a href="{{ route('bookings.search', $query(['date' => $nextDate])) }}" class="btn-warm mt-2">
                        <x-heroicon-o-arrow-right class="h-5 w-5 rtl:rotate-180" aria-hidden="true" />
                        {{ __('stable_bookings.search.next_date', ['date' => \Carbon\CarbonImmutable::parse($nextDate)->locale(app()->getLocale())->translatedFormat('l j F')]) }}
                    </a>
                @endif
            </div>
        @else
            <div class="mt-4 space-y-6">
                @foreach($results as $result)
                    @php $stable = $result['stable']; @endphp
                    <article class="card-warm overflow-hidden">
                        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                            @if($stable->cover_photo_url)
                                <img src="{{ $stable->cover_photo_url }}" alt="" class="h-20 w-full rounded-2xl object-cover sm:w-28" loading="lazy">
                            @endif
                            <div class="flex-1">
                                <h3 class="font-display text-lg font-semibold text-warm-900">
                                    <a href="{{ route('stables.show', $stable->slug) }}" class="hover:underline">{{ $stable->name }}</a>
                                </h3>
                                @php $rating = $stable->rating(); @endphp
                                @if($rating['count'] > 0)
                                    <x-stars :rating="$rating['average']" :count="$rating['count']" class="mt-1" />
                                @endif
                                <p class="mt-1 flex items-center gap-1 text-sm text-warm-700">
                                    <x-heroicon-o-map-pin class="h-4 w-4" aria-hidden="true" />
                                    {{ collect([$stable->city?->name, $stable->region?->name])->filter()->implode('، ') }}
                                </p>
                            </div>
                        </div>

                        <div class="divide-y divide-warm-100 border-t border-warm-100">
                            @foreach($result['offerings'] as $group)
                                @php $offering = $group['offering']; @endphp
                                <div class="p-5">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <a href="{{ route('bookings.offering', ['stable' => $stable->slug, 'offering' => $offering->id, 'date' => $date, 'riders' => $riders]) }}" class="flex items-center gap-2 font-semibold text-warm-900 hover:underline">
                                            <x-dynamic-component :component="$offering->type->icon()" class="h-5 w-5 text-warm-600" aria-hidden="true" />
                                            {{ $offering->name }}
                                        </a>
                                        <p class="text-sm text-warm-700">
                                            <span class="font-semibold text-warm-900">{!! \App\Models\SiteSetting::formatCurrencyHtml($offering->price, 3) !!}</span>
                                            {{ __('stable_bookings.per_rider') }} · {{ trans_choice('stable_panel.units.minutes', $offering->duration_minutes, ['count' => $offering->duration_minutes]) }}
                                        </p>
                                    </div>
                                    <ul class="mt-3 flex flex-wrap gap-2">
                                        @foreach($group['slots'] as $slot)
                                            <li>
                                                <a href="{{ route('bookings.create', ['slot' => $slot->id, 'riders' => $riders]) }}"
                                                   class="flex flex-col rounded-xl border border-warm-200 bg-white px-3 py-2 text-center transition hover:border-warm-500 hover:bg-warm-50">
                                                    <span class="font-semibold text-warm-900" dir="ltr">{{ $slot->startsAt()->format('H:i') }}</span>
                                                    <span class="text-[11px] text-warm-600">{{ trans_choice('stable_bookings.places_left', $slot->remainingPlaces(), ['count' => $slot->remainingPlaces()]) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.site>
