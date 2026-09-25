<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('farriers.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('farriers.back_to_listing') }}
        </a>

        <x-zoomable-image :src="$farrier->cover_photo_url" alt="{{ $farrier->name }}" class="mt-6 h-72 w-full rounded-3xl sm:h-96" />

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap gap-1.5">
                    @if($farrier->specialty)
                        <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $farrier->specialty }}</span>
                    @endif
                    @if($farrier->years_experience !== null)
                        <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ trans_choice('farriers.years_experience_badge', $farrier->years_experience, ['count' => $farrier->years_experience]) }}</span>
                    @endif
                </div>

                <h1 class="mt-4 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $farrier->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $farrier->city?->name }}, {{ $farrier->country?->name }}</p>
                <x-listing-meta class="mt-3" :posted-at="$farrier->created_at" :views="$farrier->views_count" />

                @if($farrier->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('farriers.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $farrier->description }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <x-currency-price :amount="$farrier->price" class="font-display text-xl font-semibold text-warm-900" />
                    <button type="button" data-reveal-contact="{{ $farrier->contact_number }}" class="js-reveal mt-4 flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-2.5 text-sm font-bold text-white">
                        {{ __('farriers.contact_reveal') }}
                    </button>
                </div>

                <x-share-buttons :url="url()->current()" :title="$farrier->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
