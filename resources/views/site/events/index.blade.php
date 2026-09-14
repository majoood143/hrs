<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
>
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        <p class="section-eyebrow">{{ __('events.navigation.plural') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-950 sm:text-4xl">
            {{ __('events.calendar.title') }}
        </h1>
        <p class="mt-3 max-w-2xl text-warm-900/70">
            {{ __('events.calendar.subtitle') }}
        </p>

        <div class="mt-8">
            <x-events-calendar />
        </div>
    </div>
</x-layouts.site>
