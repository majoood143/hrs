<x-layouts.site
    :seo-title="$seoTitle"
    :seo-description="$seoDescription"
    :seo-image="$seoImage"
    :og-type="$ogType"
    :canonical-url="$canonicalUrl"
    :noindex="$noindex"
    :nofollow="$nofollow"
    :custom-css="$page->custom_css"
    :custom-head-scripts="$page->custom_head_scripts"
    :custom-body-scripts="$page->custom_body_scripts"
>
    @if($page->show_title && !$page->is_homepage)
        <div class="mx-auto max-w-4xl px-6 pt-16 text-center">
            <h1 class="font-display text-4xl font-semibold text-warm-900">{{ $page->getTranslation('title', app()->getLocale()) }}</h1>
        </div>
    @endif

    @include('cms.render-blocks', ['blocks' => $page->content ?? []])
</x-layouts.site>
