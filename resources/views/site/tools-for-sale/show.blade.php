<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('tools-for-sale.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('tools-for-sale.back_to_listing') }}
        </a>

        <img src="{{ $tool->cover_photo_url }}" alt="{{ $tool->name }}" class="mt-6 h-72 w-full rounded-3xl object-cover sm:h-96">

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap gap-1.5">
                    <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $tool->category_label }}</span>
                    <span class="rounded-full {{ $tool->condition === 'new' ? 'bg-emerald-100 text-emerald-700' : 'bg-warm-100 text-warm-700' }} px-3 py-1 text-xs font-bold uppercase tracking-wide">{{ $tool->condition_label }}</span>
                </div>

                <h1 class="mt-4 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $tool->name }}</h1>
                @if($tool->brand)
                    <p class="mt-1 text-warm-900/60">{{ $tool->brand }}</p>
                @endif
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $tool->city?->name }}, {{ $tool->country?->name }}</p>

                @if($tool->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('tools-for-sale.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $tool->description }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <x-currency-price :amount="$tool->price" class="font-display text-xl font-semibold text-warm-900" />
                    <button type="button" data-reveal-contact="{{ $tool->contact_number }}" class="js-reveal mt-4 flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-2.5 text-sm font-bold text-white">
                        {{ __('tools-for-sale.contact_reveal') }}
                    </button>
                </div>

                <x-share-buttons :url="url()->current()" :title="$tool->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
