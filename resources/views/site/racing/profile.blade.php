@php
    $backUrl = str_starts_with(url()->previous(), route('racing.search')) ? url()->previous() : route('racing.search');
@endphp

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
        <x-racing.nav />

        <a href="{{ $backUrl }}" class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-warm-700 hover:text-warm-900 print:hidden">
            <span aria-hidden="true" class="rtl:rotate-180">&larr;</span> {{ __('racing.back') }}
        </a>

        @if($unavailable)
            <div class="mt-8 rounded-3xl border border-dashed border-warm-300 bg-white/60 px-6 py-14 text-center">
                <p class="font-display text-xl font-semibold text-warm-900">{{ __('racing.unavailable_title') }}</p>
                <p class="mt-2 text-warm-900/60">{{ __('racing.unavailable_body') }}</p>
            </div>
        @else
            <x-racing.tools :title="$profile['title'] . ' — ' . __('racing.profile_types.' . $entity)" :url="url()->current()" :pdf="route('racing.profile.pdf', [$entity, $id])" />

            <header class="card-warm mt-6 flex flex-col gap-6 p-6 sm:flex-row sm:items-start sm:justify-between sm:p-8">
                <div class="flex min-w-0 gap-5">
                    @if($profile['image'])
                        <img src="{{ route('racing.image', ['p' => $profile['image']]) }}" alt="{{ $profile['title'] }}"
                             width="88" height="88" loading="lazy"
                             class="h-22 w-22 shrink-0 rounded-2xl border border-warm-200 object-cover">
                    @endif

                    <div class="min-w-0">
                        <p class="section-eyebrow">{{ __('racing.profile_types.' . $entity) }}</p>
                        <h1 class="mt-1 font-display text-3xl font-semibold text-warm-900">{{ $profile['title'] }}</h1>

                        @if($profile['meta'] || $profile['days_since_run'])
                            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-warm-900/70">
                                @foreach($profile['meta'] as $item)
                                    <span>{{ $item }}</span>
                                @endforeach
                                @if($profile['days_since_run'])
                                    <span class="text-warm-900/50" title="{{ __('racing.days_since_run') }}">{{ $profile['days_since_run'] }}</span>
                                @endif
                            </p>
                        @endif

                        @if($profile['pedigree'])
                            <p class="mt-1 text-sm text-warm-900/60">{{ $profile['pedigree'] }}</p>
                        @endif

                        @if($profile['facts'])
                            <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                @foreach($profile['facts'] as $fact)
                                    <dt class="text-warm-900/60">{{ $fact['label'] }}</dt>
                                    <dd class="text-warm-900"><x-racing.cell :cell="['text' => $fact['value'], 'link' => $fact['link']]" /></dd>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </div>

                @if($profile['rating'])
                    <div class="shrink-0 rounded-2xl bg-warm-50 px-8 py-4 text-center">
                        <p class="font-display text-4xl font-semibold text-warm-900">{{ $profile['rating']['value'] }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('racing.rating') }}</p>
                    </div>
                @endif
            </header>

            @if($profile['tabs'])
                <div class="mt-8" data-racing-tabs>
                    <div role="tablist" class="flex flex-wrap gap-2 border-b border-warm-200">
                        @foreach($profile['tabs'] as $i => $tab)
                            <button type="button" role="tab" id="racing-tab-{{ $i }}" aria-controls="racing-panel-{{ $i }}"
                                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}"
                                    class="-mb-px rounded-t-xl border-b-2 px-4 py-3 text-sm font-semibold transition aria-selected:border-warm-600 aria-selected:text-warm-900 border-transparent text-warm-900/60 hover:text-warm-900">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    @foreach($profile['tabs'] as $i => $tab)
                        <div role="tabpanel" id="racing-panel-{{ $i }}" aria-labelledby="racing-tab-{{ $i }}" @if($i !== 0) hidden @endif class="pt-6">
                            <h2 class="mb-3 hidden font-display text-lg font-semibold text-warm-900 print:block">{{ $tab['label'] }}</h2>
                            @forelse($tab['tables'] as $table)
                                @include('site.racing._table', ['table' => $table])
                            @empty
                                <p class="rounded-2xl border border-dashed border-warm-300 bg-white/60 px-6 py-10 text-center text-warm-900/60">
                                    {{ $tab['message'] ?: __('racing.no_data') }}
                                </p>
                            @endforelse
                        </div>
                    @endforeach
                </div>

                <script>
                    document.querySelectorAll('[data-racing-tabs]').forEach(function (root) {
                        var tabs = Array.from(root.querySelectorAll('[role="tab"]'));

                        function select(tab) {
                            tabs.forEach(function (t) {
                                var on = t === tab;
                                t.setAttribute('aria-selected', on ? 'true' : 'false');
                                t.tabIndex = on ? 0 : -1;
                                document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
                            });
                            tab.focus();
                        }

                        tabs.forEach(function (tab, i) {
                            tab.addEventListener('click', function () { select(tab); });
                            tab.addEventListener('keydown', function (e) {
                                var rtl = document.documentElement.dir === 'rtl';
                                var step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[e.key];
                                if (step) { e.preventDefault(); select(tabs[(i + step + tabs.length) % tabs.length]); }
                            });
                        });
                    });
                </script>
            @endif
        @endif
    </div>
</x-layouts.site>
