@php
    $today = strtolower(now()->format('D'));
    $days = \App\Models\Clinic::DAYS;
@endphp

<x-layouts.site :seo-title="$seoTitle" :seo-description="$seoDescription ?? null" :seo-image="$seoImage ?? null">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('clinics.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('clinics.back_to_clinics') }}
        </a>

        @if($clinic->cover_photo_url)
            <img src="{{ $clinic->cover_photo_url }}" alt="{{ $clinic->name }}" class="mt-6 h-72 w-full rounded-3xl object-cover sm:h-96">
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <span class="rounded-full bg-warm-900/5 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-warm-600">{{ $clinic->type_label }}</span>
                <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $clinic->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $clinic->city?->name }}, {{ $clinic->country?->name }}</p>
                <x-listing-meta class="mt-3" :posted-at="$clinic->created_at" :views="$clinic->views_count" />

                @if($clinic->services->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($clinic->services as $service)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($clinic->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('clinics.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $clinic->description }}</p>
                    </div>
                @endif

                @if(!empty($clinic->gallery_urls))
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('clinics.gallery') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($clinic->gallery_urls as $image)
                                <img src="{{ $image }}" alt="{{ $clinic->name }}" class="h-32 w-full rounded-xl object-cover">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('clinics.address') }}</h2>
                    @if($clinic->address)
                        <p class="mt-2 text-sm text-warm-900/80">{{ $clinic->address }}</p>
                    @endif
                    @if($clinic->map_link)
                        <a href="{{ $clinic->map_link }}" target="_blank" rel="noopener" class="btn-warm-outline mt-3 inline-flex w-full justify-center !py-2 text-sm">
                            {{ __('clinics.open_in_maps') }}
                        </a>
                    @endif
                </div>

                @if($clinic->phone || $clinic->website_url || $clinic->instagram_url)
                    <div class="card-warm p-5">
                        <h2 class="font-display text-base font-semibold text-warm-900">{{ __('clinics.contact') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @if($clinic->phone)
                                <li>
                                    <a href="tel:{{ $clinic->phone }}" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📞 <span>{{ $clinic->phone }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($clinic->website_url)
                                <li>
                                    <a href="{{ $clinic->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        🌐 <span>{{ __('clinics.website') }}</span>
                                    </a>
                                </li>
                            @endif
                            @if($clinic->instagram_url)
                                <li>
                                    <a href="{{ $clinic->instagram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-warm-900/80 hover:text-warm-700">
                                        📷 <span>{{ __('clinics.instagram') }}</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="card-warm p-5">
                    <h2 class="font-display text-base font-semibold text-warm-900">{{ __('clinics.opening_hours') }}</h2>
                    <dl class="mt-3 divide-y divide-warm-200 text-sm">
                        @foreach($days as $day)
                            @php
                                $closed = data_get($clinic->opening_hours, "{$day}.closed", true);
                                $opensAt = data_get($clinic->opening_hours, "{$day}.opens_at");
                                $closesAt = data_get($clinic->opening_hours, "{$day}.closes_at");
                                $isToday = $day === $today;
                            @endphp
                            <div class="flex items-center justify-between py-2 {{ $isToday ? 'font-semibold text-warm-900' : 'text-warm-900/70' }}">
                                <dt>{{ __('clinics.days.' . $day) }}</dt>
                                <dd>
                                    @if($closed || !$opensAt || !$closesAt)
                                        {{ __('clinics.closed') }}
                                    @else
                                        {{ $opensAt }} – {{ $closesAt }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <x-share-buttons :url="url()->current()" :title="$clinic->name" />
            </div>
        </div>
    </div>
</x-layouts.site>
