<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
>
    <div class="mx-auto max-w-6xl px-6 py-14">
        <x-racing.nav active="calendar" />

        <div class="mx-auto mt-10 max-w-2xl text-center" data-reveal>
            <p class="section-eyebrow">{{ __('racing.eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900 sm:text-4xl">{{ __('racing.calendar.title') }}</h1>
            <p class="mt-3 text-warm-900/70">{{ __('racing.calendar.subtitle') }}</p>
        </div>

        @if($notice)
            <div class="mt-8 rounded-2xl border border-amber-300 bg-amber-50 px-6 py-4 text-sm text-amber-900" role="alert">{{ $notice }}</div>
        @endif

        <div class="mt-10">
            <x-racing.calendar />
        </div>
    </div>
</x-layouts.site>
