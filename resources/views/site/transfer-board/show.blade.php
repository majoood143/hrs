<x-layouts.site :seo-title="$seoTitle">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('transfer-board.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('transportation.back_to_listing') }}
        </a>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if($post->cover_photo_url)
                    <img src="{{ $post->cover_photo_url }}" alt="" class="h-72 w-full rounded-3xl object-cover sm:h-96">
                @endif

                <span class="mt-6 inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $post->type === 'offer' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $post->type === 'offer' ? __('transportation.type_offer') : __('transportation.type_request') }}
                </span>

                <p class="section-eyebrow mt-4">{{ __('transportation.transfer_details_title') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">
                    {{ $post->fromCity?->name }}, {{ $post->fromCountry?->name }}
                    <span aria-hidden="true">→</span>
                    {{ $post->toCity?->name }}, {{ $post->toCountry?->name }}
                </p>

                <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="card-warm p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.transfer_date') }}</dt>
                        <dd class="mt-1 font-display text-lg font-semibold text-warm-900">📅 {{ $post->transfer_date->format('M d, Y') }}</dd>
                    </div>
                    <div class="card-warm p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('transportation.capacity') }}</dt>
                        <dd class="mt-1 font-display text-lg font-semibold text-warm-900">🐎 {{ trans_choice('transportation.spaces_available', $post->capacity, ['count' => $post->capacity]) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    @if($post->type === 'offer' && filled($post->price))
                        <x-currency-price :amount="$post->price" class="font-display text-xl font-semibold text-warm-900" />
                    @else
                        <span class="font-display text-xl font-semibold text-warm-900">{{ __('transportation.budget_open') }}</span>
                    @endif
                    <button type="button" data-reveal-contact="{{ $post->contact_number }}" class="js-reveal mt-4 flex w-full items-center justify-center rounded-full bg-warm-900 px-4 py-2.5 text-sm font-bold text-white">
                        {{ __('transportation.contact_reveal') }}
                    </button>
                </div>

                <x-share-buttons :url="url()->current()" :title="__('transportation.transfer_details_title') . ': ' . $post->fromCity?->name . ' → ' . $post->toCity?->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
