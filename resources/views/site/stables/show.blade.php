@php
    $today = strtolower(now()->format('D'));
    $days = \App\Models\Stable::DAYS;
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('stables.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('stables.back_to_stables') }}
        </a>

        @if($stable->cover_photo_url)
            <x-zoomable-image :src="$stable->cover_photo_url" alt="{{ $stable->name }}" class="mt-6 h-72 w-full rounded-3xl sm:h-96" />
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $stable->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $stable->city?->name }}, {{ $stable->country?->name }}</p>
                @php $rating = $stable->rating(); @endphp
                @if($rating['count'] > 0)
                    <a href="#reviews" class="mt-2 inline-block"><x-stars :rating="$rating['average']" :count="$rating['count']" /></a>
                @endif

                @if($stable->services->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($stable->services as $service)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                        @endforeach
                    </div>
                @endif

                @php $bookable = $stable->acceptsBookings() ? $stable->offerings()->active()->orderBy('en_name')->get() : collect(); @endphp
                @if($bookable->isNotEmpty())
                    <section class="mt-8" aria-labelledby="book-heading">
                        <h2 id="book-heading" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900">
                            <x-heroicon-o-calendar-days class="h-6 w-6 text-warm-600" aria-hidden="true" />
                            {{ __('stable_bookings.stable_page.heading') }}
                        </h2>
                        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach($bookable as $offering)
                                <li class="card-warm flex flex-col gap-3 p-5">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warm-50 text-warm-600 ring-4 ring-warm-100">
                                            <x-dynamic-component :component="$offering->type->icon()" class="h-5 w-5" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <p class="font-semibold text-warm-900">{{ $offering->name }}</p>
                                            <p class="text-sm text-warm-700">
                                                {!! \App\Models\SiteSetting::formatCurrencyHtml($offering->price, 3) !!} {{ __('stable_bookings.per_rider') }}
                                                · {{ trans_choice('stable_panel.units.minutes', $offering->duration_minutes, ['count' => $offering->duration_minutes]) }}
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ route('bookings.offering', ['stable' => $stable->slug, 'offering' => $offering->id]) }}" class="btn-warm mt-auto justify-center !py-2 text-sm">
                                        <x-heroicon-o-calendar class="h-5 w-5" aria-hidden="true" />
                                        {{ __('stable_bookings.stable_page.pick_time') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if($stable->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('stables.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $stable->description }}</p>
                    </div>
                @endif

                @php $reviews = $stable->reviews()->visible()->with('offering')->latest('id')->take(10)->get(); @endphp
                @if($reviews->isNotEmpty())
                    <section id="reviews" class="mt-8" aria-labelledby="reviews-heading">
                        <h2 id="reviews-heading" class="flex items-center gap-2 font-display text-xl font-semibold text-warm-900">
                            <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-warm-600" aria-hidden="true" />
                            {{ __('stable_reviews.heading') }}
                        </h2>
                        <ul class="mt-3 space-y-3">
                            @foreach($reviews as $review)
                                <li class="card-warm p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-semibold text-warm-900">{{ $review->displayName() }}</p>
                                        <x-stars :rating="$review->rating" />
                                    </div>
                                    <p class="mt-0.5 text-xs text-warm-600">{{ $review->offering?->name }} · <span dir="ltr">{{ $review->created_at->format('Y-m-d') }}</span></p>
                                    @if($review->comment)
                                        <p class="mt-2 whitespace-pre-line text-sm text-warm-900/80">{{ $review->comment }}</p>
                                    @endif
                                    @if($review->reply)
                                        <div class="mt-3 flex gap-2 rounded-xl bg-warm-50 p-3 text-sm text-warm-800">
                                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4 shrink-0 rtl:rotate-180" aria-hidden="true" />
                                            <p><span class="font-semibold">{{ __('stable_reviews.reply_from', ['stable' => $stable->name]) }}</span> {{ $review->reply }}</p>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if(!empty($stable->gallery_urls))
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('stables.gallery') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($stable->gallery_urls as $image)
                                <x-zoomable-image :src="$image" :alt="$stable->name" fit="cover" group="gallery" class="h-32 w-full rounded-xl" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('stables.address') }}</h2>
                    @if($stable->address)
                        <p class="mt-2 text-sm text-warm-900/80">{{ $stable->address }}</p>
                    @endif
                    @if($stable->map_link)
                        <a href="{{ $stable->map_link }}" target="_blank" rel="noopener" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('stables.open_in_maps') }}
                        </a>
                    @endif
                </div>

                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('stables.opening_hours') }}</h2>
                    <dl class="mt-3 divide-y divide-warm-200 text-sm">
                        @foreach($days as $day)
                            @php
                                $closed = data_get($stable->opening_hours, "{$day}.closed", true);
                                $opensAt = data_get($stable->opening_hours, "{$day}.opens_at");
                                $closesAt = data_get($stable->opening_hours, "{$day}.closes_at");
                                $isToday = $day === $today;
                            @endphp
                            <div class="flex items-center justify-between py-2 {{ $isToday ? 'font-semibold text-warm-900' : 'text-warm-900/70' }}">
                                <dt>{{ __('stables.days.' . $day) }}</dt>
                                <dd>
                                    @if($closed || !$opensAt || !$closesAt)
                                        {{ __('stables.closed') }}
                                    @else
                                        {{ $opensAt }} – {{ $closesAt }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <x-share-buttons :url="url()->current()" :title="$stable->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
