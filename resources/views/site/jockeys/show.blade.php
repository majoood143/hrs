<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('jockeys.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('jockeys.back_to_listing') }}
        </a>

        @include('site.jockeys._photo', ['jockey' => $jockey, 'class' => 'mt-6 h-72 w-full rounded-3xl sm:h-96'])

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @include('site.jockeys._badges', ['jockey' => $jockey, 'size' => 'px-3 py-1 text-xs'])

                <h1 class="mt-4 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $jockey->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $jockey->city?->name }}, {{ $jockey->country?->name }}</p>
                <x-listing-meta class="mt-3" :posted-at="$jockey->created_at" :views="$jockey->views_count" />

                @if($jockey->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('jockeys.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $jockey->description }}</p>
                    </div>
                @endif

                @if($jockey->verified_racing_id)
                    <div class="mt-8 card-warm p-5">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('jockeys.tabs.racing') }}</h2>
                        <p class="mt-2 text-sm text-warm-900/70">{{ __('jockeys.racing_record_body') }}</p>
                        <a href="{{ route('racing.profile', ['entity' => 'jockey', 'id' => $jockey->verified_racing_id]) }}" class="btn-warm mt-4 inline-flex">
                            {{ __('jockeys.racing_record_link') }}
                        </a>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <button type="button" data-reveal-contact="{{ $jockey->contact_number }}" class="js-reveal flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-2.5 text-sm font-bold text-white">
                        {{ __('jockeys.contact_reveal') }}
                    </button>
                </div>

                <x-share-buttons :url="url()->current()" :title="$jockey->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
