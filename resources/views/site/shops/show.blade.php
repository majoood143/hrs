@php
    $today = strtolower(now()->format('D'));
    $days = \App\Models\Shop::DAYS;
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('shops.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('shops.back_to_shops') }}
        </a>

        @if($shop->cover_photo_url)
            <img src="{{ $shop->cover_photo_url }}" alt="{{ $shop->name }}" class="mt-6 h-72 w-full rounded-3xl object-cover sm:h-96">
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-warm-900/5 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-600">{{ $shop->type_label }}</span>
                    @if($shop->is_online)
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700">🌐 {{ __('shops.online_badge') }}</span>
                    @endif
                </div>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $shop->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $shop->city?->name }}, {{ $shop->country?->name }}</p>

                @if($shop->services->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($shop->services as $service)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($shop->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('shops.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $shop->description }}</p>
                    </div>
                @endif

                @if(!empty($shop->gallery_urls))
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('shops.gallery') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($shop->gallery_urls as $image)
                                <img src="{{ $image }}" alt="{{ $shop->name }}" class="h-32 w-full rounded-xl object-cover">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                @if($shop->is_online)
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('shops.online_shop') }}</h2>
                        @if($shop->delivery_scope_label)
                            <p class="mt-2 flex items-center gap-2 text-sm text-warm-900/80">🚚 <span>{{ $shop->delivery_scope_label }}</span></p>
                        @endif
                    </div>
                @else
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('shops.address') }}</h2>
                        @if($shop->address)
                            <p class="mt-2 text-sm text-warm-900/80">{{ $shop->address }}</p>
                        @endif
                        @if($shop->map_link)
                            <a href="{{ $shop->map_link }}" target="_blank" rel="noopener" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                                {{ __('shops.open_in_maps') }}
                            </a>
                        @endif
                    </div>
                @endif

                @if(!empty($shop->payment_option_labels))
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('shops.payment_options') }}</h2>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($shop->payment_option_labels as $label)
                                <span class="rounded-full bg-warm-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-700">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($shop->phone || $shop->website_url || $shop->instagram_url)
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('shops.contact') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @if($shop->phone)
                                <li>
                                    <a href="tel:{{ $shop->phone }}" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📞 <span>{{ $shop->phone }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($shop->website_url)
                                <li>
                                    <a href="{{ $shop->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        🌐 <span>{{ __('shops.website') }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($shop->instagram_url)
                                <li>
                                    <a href="{{ $shop->instagram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📷 <span>{{ __('shops.instagram') }}</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('shops.opening_hours') }}</h2>
                    <dl class="mt-3 divide-y divide-warm-200 text-sm">
                        @foreach($days as $day)
                            @php
                                $closed = data_get($shop->opening_hours, "{$day}.closed", true);
                                $opensAt = data_get($shop->opening_hours, "{$day}.opens_at");
                                $closesAt = data_get($shop->opening_hours, "{$day}.closes_at");
                                $isToday = $day === $today;
                            @endphp
                            <div class="flex items-center justify-between py-2 {{ $isToday ? 'font-semibold text-warm-900' : 'text-warm-900/70' }}">
                                <dt>{{ __('shops.days.' . $day) }}</dt>
                                <dd>
                                    @if($closed || !$opensAt || !$closesAt)
                                        {{ __('shops.closed') }}
                                    @else
                                        {{ $opensAt }} – {{ $closesAt }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <x-share-buttons :url="url()->current()" :title="$shop->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
