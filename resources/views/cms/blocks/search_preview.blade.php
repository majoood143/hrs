@php
    $countries = \App\Models\Country::query()->public()->orderBy('en_name')->get();
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('Find a safe ride for your horse');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

<section class="mx-auto max-w-5xl px-6 py-16" data-reveal>
    <div class="card-warm -mt-24 relative z-10 p-6 sm:p-8">
        <div class="mb-6 text-center">
            <h2 class="font-display text-2xl font-semibold text-warm-900 sm:text-3xl">
                {{ $heading }}
            </h2>
            @if($subheading)
                <p class="mt-2 text-warm-900/70">{{ $subheading }}</p>
            @endif
        </div>

        <form data-search-preview action="{{ url('/transfer-board') }}" method="GET" class="grid gap-4 md:grid-cols-4">
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('From') }}</label>
                <select data-field="from_country_id" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('Any country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('To') }}</label>
                <select data-field="to_country_id" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
                    <option value="">{{ __('Any country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('Date') }}</label>
                <input type="date" data-field="transfer_date" class="w-full rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900 focus:border-warm-500 focus:outline-none focus:ring-2 focus:ring-warm-300">
            </div>

            <div class="flex items-end md:col-span-1">
                <button type="submit" class="btn-warm w-full justify-center">
                    {{ __('Search Transfers') }}
                </button>
            </div>
        </form>

        <p class="mt-4 text-center text-xs text-warm-900/50">
            {{ __('Or') }}
            <a href="{{ route('transfer-board.create') }}" class="font-semibold text-warm-700 underline decoration-warm-300 underline-offset-4 hover:text-warm-800">
                {{ __('post a transfer offer or request') }}
            </a>
        </p>
    </div>
</section>
