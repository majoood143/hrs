@php
    use App\Services\Racing\RacingSearchType;

    $selectedType = RacingSearchType::fromInput($type instanceof \BackedEnum ? $type->value : ($type ?? null));
    $fieldId = 'racing-q-' . ($idSuffix ?? 'main');
@endphp

<form action="{{ route('racing.search') }}" method="GET" data-racing-search class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
    <div>
        <label for="{{ $fieldId }}" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.query_label') }}</label>
        <input id="{{ $fieldId }}" type="search" name="q" value="{{ $q ?? '' }}" required
               minlength="{{ config('racing.min_query_length') }}" maxlength="{{ config('racing.max_query_length') }}"
               placeholder="{{ __('racing.placeholder') }}" autocomplete="off" dir="auto"
               class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
    </div>

    <div>
        <label for="{{ $fieldId }}-type" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.type_label') }}</label>
        <select id="{{ $fieldId }}-type" name="type"
                class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            @foreach(RacingSearchType::cases() as $case)
                <option value="{{ $case->value }}" @selected($case === $selectedType)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end">
        <button type="submit" data-racing-search-button class="btn-warm w-full justify-center aria-busy:cursor-wait aria-busy:opacity-80">
            <x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" data-racing-search-idle />
            <svg class="hidden h-5 w-5 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true" data-racing-search-busy>
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            <span data-racing-search-label data-busy-label="{{ __('racing.searching') }}">{{ __('racing.search') }}</span>
        </button>
    </div>
</form>

<p class="mt-3 text-xs text-warm-900/50">{{ __('racing.hint', ['min' => config('racing.min_query_length')]) }}</p>

@include('site.racing._loading')
