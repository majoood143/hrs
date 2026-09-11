<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('horses-for-sale.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('horses-for-sale.back_to_listing') }}
        </a>

        <img src="{{ $post->cover_photo_url }}" alt="{{ $post->name }}" class="mt-6 h-72 w-full rounded-3xl object-cover sm:h-96">

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap gap-1.5">
                    <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $post->type?->name }}</span>
                    <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $post->gender?->name }}</span>
                    @if($post->age !== null)
                        <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">
                            @if($post->age >= 1)
                                {{ trans_choice('horses-for-sale.years_old', $post->age, ['count' => $post->age]) }}
                            @else
                                {{ trans_choice('horses-for-sale.months_old', max(1, $post->age_in_months), ['count' => max(1, $post->age_in_months)]) }}
                            @endif
                        </span>
                    @endif
                </div>

                <h1 class="mt-4 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $post->name }}</h1>
                @if($post->breed)
                    <p class="mt-1 text-warm-900/60">{{ $post->breed }}</p>
                @endif
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $post->city?->name }}, {{ $post->country?->name }}</p>

                @if($post->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('horses-for-sale.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $post->description }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <x-currency-price :amount="$post->price" class="font-display text-xl font-semibold text-warm-900" />
                    <button type="button" data-reveal-contact="{{ $post->contact_number }}" class="js-reveal mt-4 flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-2.5 text-sm font-bold text-white">
                        {{ __('horses-for-sale.contact_reveal') }}
                    </button>
                </div>

                <x-share-buttons :url="url()->current()" :title="$post->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
