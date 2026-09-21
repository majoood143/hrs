@php
    use App\Services\Racing\RacingSearchType;

    $selectedType = RacingSearchType::fromInput($type instanceof \BackedEnum ? $type->value : ($type ?? null));
    $fieldId = 'racing-q-' . ($idSuffix ?? 'main');
@endphp

<form action="{{ route('racing.search') }}" method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
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
        <button type="submit" class="btn-warm w-full justify-center">{{ __('racing.search') }}</button>
    </div>
</form>

<p class="mt-3 text-xs text-warm-900/50">{{ __('racing.hint', ['min' => config('racing.min_query_length')]) }}</p>
