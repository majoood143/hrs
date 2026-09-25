@php
    $today = strtolower(now()->format('D'));
    $days = \App\Models\Center::DAYS;
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('centers.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('centers.back_to_centers') }}
        </a>

        @if($center->cover_photo_url)
            <x-zoomable-image :src="$center->cover_photo_url" alt="{{ $center->name }}" class="mt-6 h-72 w-full rounded-3xl sm:h-96" />
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <span class="rounded-full bg-warm-900/5 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-600">{{ $center->type_label }}</span>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $center->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $center->city?->name }}, {{ $center->country?->name }}</p>
                <x-listing-meta class="mt-3" :posted-at="$center->created_at" :views="$center->views_count" />

                @if($center->services->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($center->services as $service)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($center->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('centers.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $center->description }}</p>
                    </div>
                @endif

                @if(!empty($center->gallery_urls))
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('centers.gallery') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($center->gallery_urls as $image)
                                <x-zoomable-image :src="$image" :alt="$center->name" fit="cover" group="gallery" class="h-32 w-full rounded-xl" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('centers.address') }}</h2>
                    @if($center->address)
                        <p class="mt-2 text-sm text-warm-900/80">{{ $center->address }}</p>
                    @endif
                    @if($center->map_link)
                        <a href="{{ $center->map_link }}" target="_blank" rel="noopener" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('centers.open_in_maps') }}
                        </a>
                    @endif
                </div>

                @if($center->phone || $center->website_url || $center->instagram_url)
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('centers.contact') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @if($center->phone)
                                <li>
                                    <a href="tel:{{ $center->phone }}" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📞 <span>{{ $center->phone }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($center->website_url)
                                <li>
                                    <a href="{{ $center->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        🌐 <span>{{ __('centers.website') }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($center->instagram_url)
                                <li>
                                    <a href="{{ $center->instagram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📷 <span>{{ __('centers.instagram') }}</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('centers.opening_hours') }}</h2>
                    <dl class="mt-3 divide-y divide-warm-200 text-sm">
                        @foreach($days as $day)
                            @php
                                $closed = data_get($center->opening_hours, "{$day}.closed", true);
                                $opensAt = data_get($center->opening_hours, "{$day}.opens_at");
                                $closesAt = data_get($center->opening_hours, "{$day}.closes_at");
                                $isToday = $day === $today;
                            @endphp
                            <div class="flex items-center justify-between py-2 {{ $isToday ? 'font-semibold text-warm-900' : 'text-warm-900/70' }}">
                                <dt>{{ __('centers.days.' . $day) }}</dt>
                                <dd>
                                    @if($closed || !$opensAt || !$closesAt)
                                        {{ __('centers.closed') }}
                                    @else
                                        {{ $opensAt }} – {{ $closesAt }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <x-share-buttons :url="url()->current()" :title="$center->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
