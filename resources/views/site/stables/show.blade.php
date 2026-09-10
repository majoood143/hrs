@php
    $today = strtolower(now()->format('D'));
    $days = \App\Models\Stable::DAYS;
@endphp

<x-layouts.site :seo-title="$seoTitle">
    <div class="mx-auto max-w-5xl px-6 py-14">
        <a href="{{ route('stables.index') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">
            &larr; {{ __('stables.back_to_stables') }}
        </a>

        @if($stable->cover_photo_url)
            <img src="{{ $stable->cover_photo_url }}" alt="{{ $stable->name }}" class="mt-6 h-72 w-full rounded-3xl object-cover sm:h-96">
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ $stable->name }}</h1>
                <p class="mt-2 text-sm text-warm-900/60">📍 {{ $stable->city?->name }}, {{ $stable->country?->name }}</p>

                @if($stable->services->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($stable->services as $service)
                            <span class="rounded-full bg-warm-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-warm-700">{{ $service->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($stable->description)
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('stables.description') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-warm-900/80">{{ $stable->description }}</p>
                    </div>
                @endif

                @if(!empty($stable->gallery_urls))
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-warm-900">{{ __('stables.gallery') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($stable->gallery_urls as $image)
                                <img src="{{ $image }}" alt="{{ $stable->name }}" class="h-32 w-full rounded-xl object-cover">
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
            </div>
        </div>
    </div>
</x-layouts.site>
