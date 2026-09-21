@php
    $params = fn (array $extra = []) => array_filter(array_merge(['breed' => $breed, 'location' => $location, 'q' => $q, 'sort' => $sort, 'dir' => $dir], $extra), fn ($v) => $v !== '');
    $sortUrl = fn (string $col) => route('racing.handicap', $params(['sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc']));
    $columns = ['name' => 'horse', 'rating' => 'rating', 'rating_date' => 'rating_date', 'last_ran' => 'last_ran'];
    $field = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300';
    $date = fn (?string $d) => $d ? \Carbon\Carbon::parse($d)->translatedFormat('j M Y') : '—';
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
    <div class="mx-auto max-w-6xl px-6 py-14">
        <x-racing.nav active="handicap" />

        <div class="mx-auto mt-10 max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('racing.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('racing.pages.handicap.title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('racing.pages.handicap.subtitle') }}</p>
        </div>

        <form action="{{ route('racing.handicap') }}" method="GET" class="card-warm mt-10 grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-[1fr_12rem_12rem_auto] lg:p-8">
            <div>
                <label for="hcap-q" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.handicap.search_horse') }}</label>
                <input id="hcap-q" type="search" name="q" value="{{ $q }}" maxlength="{{ config('racing.max_query_length') }}" autocomplete="off" dir="auto" class="{{ $field }}">
            </div>
            <div>
                <label for="hcap-breed" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.handicap.breed') }}</label>
                <select id="hcap-breed" name="breed" class="{{ $field }}">
                    @foreach(['all', 'tb', 'pa'] as $option)
                        <option value="{{ $option }}" @selected($breed === $option)>{{ __('racing.handicap.breeds.' . $option) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hcap-location" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.handicap.location') }}</label>
                <select id="hcap-location" name="location" class="{{ $field }}">
                    @foreach(['all', 'local', 'non-local'] as $option)
                        <option value="{{ $option }}" @selected($location === $option)>{{ __('racing.handicap.locations.' . $option) }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">
            <div class="flex items-end">
                <button type="submit" class="btn-warm w-full justify-center">{{ __('racing.search') }}</button>
            </div>
        </form>

        @if($unavailable)
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
            </div>
        @elseif($horses->total() === 0)
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.no_results_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.no_results_body') }}</p>
            </div>
        @else
            <div class="mt-8 overflow-x-auto rounded-2xl border border-warm-200/70 bg-white shadow-sm shadow-warm-900/5">
                <table class="min-w-full text-sm">
                    <thead class="bg-warm-50 text-xs font-semibold uppercase tracking-wide text-warm-700">
                        <tr>
                            @foreach($columns as $col => $label)
                                <th scope="col" class="px-4 py-3 text-start" @if($sort === $col) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ $sortUrl($col) }}" class="inline-flex items-center gap-1 hover:text-warm-900">
                                        {{ __('racing.handicap.columns.' . $label) }}
                                        @if($sort === $col)<span aria-hidden="true">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-warm-100 text-warm-900">
                        @foreach($horses as $horse)
                            <tr class="hover:bg-warm-50/60">
                                <td class="px-4 py-3"><x-racing.entity type="horse" :entity="$horse" /></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if($horse['rating'] !== null)
                                        <span class="font-semibold">{{ $horse['rating'] }}</span>
                                        @if($horse['change'])
                                            <span class="ms-1 text-xs {{ $horse['change'] > 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                                <span aria-hidden="true">{{ $horse['change'] > 0 ? '▲' : '▼' }}</span>{{ abs($horse['change']) }}
                                                <span class="sr-only">{{ $horse['change'] > 0 ? __('racing.handicap.up') : __('racing.handicap.down') }}</span>
                                            </span>
                                        @endif
                                    @elseif($horse['label'])
                                        <span title="{{ __('racing.handicap.nor') }}">{{ $horse['label'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $date($horse['rating_date']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $date($horse['last_ran']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                <p class="text-sm text-warm-900/60">
                    {{ __('racing.showing', ['from' => $horses->firstItem(), 'to' => $horses->lastItem(), 'total' => number_format($horses->total())]) }}
                </p>
                {{ $horses->links() }}
            </div>
        @endif
    </div>
</x-layouts.site>
