<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
>
    <div class="mx-auto max-w-6xl px-6 py-14" data-racing-print>
        <x-racing.nav :active="$page" :carry="$carry" />

        <div class="mx-auto mt-10 max-w-3xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('racing.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('racing.pages.' . $page . '.title') }}</h1>
            @if($meeting['heading'] ?? null)
                <p class="mt-3 text-warm-900/70">{{ $meeting['heading'] }}</p>
            @endif
        </div>

        @if($unavailable)
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
            </div>
        @elseif(! $selected)
            <div class="mt-10 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.no_meeting_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.no_meeting_body') }}</p>
            </div>
        @else
            {{-- one tab per race in the meeting; each is its own URL so a race can be shared / printed on its own --}}
            <nav aria-label="{{ __('racing.races_in_meeting') }}" class="mt-10 overflow-x-auto print:hidden">
                <ul class="flex min-w-max gap-2 border-b border-warm-200">
                    @foreach($meeting['races'] as $r)
                        <li>
                            <a href="{{ route('racing.meeting', ['page' => $page, 'race' => $r['id']]) }}" title="{{ $r['title'] }}"
                               @if($r['id'] === $selected['id']) aria-current="page" @endif
                               class="-mb-px inline-block whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold transition
                                      {{ $r['id'] === $selected['id'] ? 'border-warm-600 text-warm-900' : 'border-transparent text-warm-900/60 hover:text-warm-900' }}">
                                {{ __('racing.race_n', ['n' => $r['number'] ?? $loop->iteration]) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-racing.tools
                :title="__('racing.pages.' . $page . '.title') . ' — ' . $selected['title']"
                :url="route('racing.meeting', ['page' => $page, 'race' => $selected['id']])"
                :pdf="($detail['available'] ?? false) ? route('racing.meeting.pdf', ['page' => $page, 'race' => $selected['id']]) : null" />

            @include('site.racing._race-header', ['race' => $selected])

            <section class="mt-8" aria-label="{{ __('racing.pages.' . $page . '.title') }}">
                @if($detail['available'] ?? false)
                    @include('site.racing._' . $page, ['detail' => $detail])
                @else
                    <p class="rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center text-warm-900/60">
                        {{ __('racing.pages.' . $page . '.unavailable') }}
                    </p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.site>
