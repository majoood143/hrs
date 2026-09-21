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
        <x-racing.nav active="search" />

        <div class="mx-auto mt-10 max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('racing.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('racing.title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('racing.subtitle') }}</p>
        </div>

        <div class="card-warm mt-10 p-6 sm:p-8">
            @include('site.racing._form', ['type' => $type, 'q' => $q])
        </div>

        @if($error)
            <div class="mt-8 rounded-2xl border border-amber-300 bg-amber-50 px-6 py-4 text-sm text-amber-900" role="alert">{{ $error }}</div>
        @elseif($unavailable)
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
            </div>
        @elseif($q !== '')
            <p class="mt-10 text-warm-900/70">{{ __('racing.results_for', ['q' => $q]) }}</p>

            @forelse($sections as $section)
                @php($paginated = $section['rows'] instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)

                <section class="mt-6" aria-labelledby="racing-{{ $section['group'] }}">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="racing-{{ $section['group'] }}" class="font-display text-xl font-semibold text-warm-900">
                            {{ __('racing.types.' . $section['group']) }}
                            <span class="text-sm font-normal text-warm-900/50">({{ number_format($section['total']) }})</span>
                        </h2>

                        @if($section['more_url'])
                            <a href="{{ $section['more_url'] }}" class="text-sm font-semibold text-warm-700 underline decoration-warm-300 underline-offset-4 hover:text-warm-900">
                                {{ __('racing.view_all', ['count' => number_format($section['total'])]) }}
                            </a>
                        @endif
                    </div>

                    <div class="mt-3 overflow-x-auto rounded-2xl border border-warm-200/70 bg-white shadow-sm shadow-warm-900/5">
                        <table class="min-w-full text-sm">
                            <thead class="bg-warm-50 text-start text-xs font-semibold uppercase tracking-wide text-warm-700">
                                <tr>
                                    @foreach($section['headers'] as $header)
                                        <th scope="col" class="px-4 py-3 text-start">{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-warm-100 text-warm-900">
                                @foreach($section['rows'] as $row)
                                    <tr class="hover:bg-warm-50/60">
                                        @foreach($row['cells'] as $i => $cell)
                                            <td class="px-4 py-3">
                                                @if($i === 0)
                                                    <x-racing.cell :cell="['text' => $cell, 'link' => ['type' => $section['group'], 'id' => $row['id']]]" />
                                                @else
                                                    {{ $cell }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($paginated)
                        <div class="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                            <p class="text-sm text-warm-900/60">
                                {{ __('racing.showing', ['from' => $section['rows']->firstItem(), 'to' => $section['rows']->lastItem(), 'total' => number_format($section['total'])]) }}
                            </p>
                            {{ $section['rows']->links() }}
                        </div>
                    @endif
                </section>
            @empty
                <div class="mt-6 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                    <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.no_results_title') }}</p>
                    <p class="mt-2 text-warm-900/60">{{ __('racing.no_results_body') }}</p>
                </div>
            @endforelse
        @endif
    </div>
</x-layouts.site>
