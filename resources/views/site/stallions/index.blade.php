@php
    $input = 'w-full rounded-xl border border-warm-200 bg-warm-50 px-3 py-2.5 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300';
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription">
    <div class="mx-auto max-w-7xl px-6 py-14">
        <div class="max-w-2xl" data-reveal>
            <h1 class="font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('stallions.board_title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('stallions.board_subtitle') }}</p>
        </div>

        <form method="GET" action="{{ route('stallions.index') }}" class="mt-8 flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="stallion-q">{{ __('stallions.search') }}</label>
            <input id="stallion-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('stallions.search_placeholder') }}" class="{{ $input }} sm:max-w-sm">
            <label class="sr-only" for="stallion-colour">{{ __('stallions.colour') }}</label>
            <select id="stallion-colour" name="colour" data-auto-submit class="{{ $input }} sm:max-w-[12rem]">
                <option value="">{{ __('stallions.any_colour') }}</option>
                @foreach(\App\Models\Stallion::colourOptions() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['colour'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-warm">{{ __('stallions.search') }}</button>
            @if($filters)
                <a href="{{ route('stallions.index') }}" class="btn-warm-outline">{{ __('stallions.clear') }}</a>
            @endif
        </form>

        <p class="mt-6 text-sm text-warm-900/60">{{ trans_choice('stallions.results_count', $stallions->total(), ['count' => $stallions->total()]) }}</p>

        @if($stallions->isEmpty())
            <div class="mt-6 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-16 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('stallions.no_results_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('stallions.no_results_body') }}</p>
            </div>
        @else
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($stallions as $stallion)
                    <a href="{{ route('stallions.show', $stallion->id) }}" class="card-warm group block overflow-hidden p-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-warm-500">
                        {{-- a plain photo: the zoomable one is a link itself and cannot sit inside the card's link --}}
                        @if($stallion->photo_url)
                            <img src="{{ $stallion->photo_url }}" alt="" loading="lazy" class="h-56 w-full object-cover">
                        @else
                            <div class="flex h-56 w-full items-center justify-center bg-warm-100 font-display text-5xl font-semibold text-warm-400" aria-hidden="true">{{ mb_substr($stallion->name, 0, 1) }}</div>
                        @endif

                        <div class="p-5">
                            <p class="font-display text-xl font-semibold text-warm-900 group-hover:text-warm-700">{{ $stallion->name }}</p>
                            @if($stallion->sire || $stallion->dam)
                                <p class="mt-1 text-sm text-warm-900/80">{{ __('stallions.by', ['sire' => $stallion->sire, 'dam' => $stallion->dam]) }}</p>
                            @endif
                            @include('site.stallions._facts', ['stallion' => $stallion, 'class' => 'mt-1 text-xs text-warm-900/60'])
                            @include('site.stallions._record', ['stallion' => $stallion, 'class' => 'mt-4 border-t border-dashed border-warm-200 pt-4 text-sm'])
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $stallions->links() }}</div>
        @endif
    </div>
</x-layouts.site>
