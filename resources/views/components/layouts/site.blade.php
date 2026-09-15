<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @if ($gtmId = \App\Models\SiteSetting::get('google_tag_manager_id'))
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '{{ $gtmId }}');
        </script>
    @endif

    <title>{{ $seoTitle ?? \App\Models\SiteSetting::siteName() }}</title>

    @if ($faviconUrl = \App\Models\SiteSetting::faviconUrl())
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    @if (!empty($seoDescription))
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if (!empty($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    @if (!empty($noindex))
        <meta name="robots" content="noindex{{ !empty($nofollow) ? ',nofollow' : '' }}">
    @endif

    @php
        $ogSiteName = \App\Models\SiteSetting::siteName();
        $resolvedOgImage = $seoImage ?? \App\Support\Seo::defaultImage();
        $resolvedOgType = $ogType ?? 'website';
    @endphp

    <meta property="og:type" content="{{ $resolvedOgType }}">
    <meta property="og:site_name" content="{{ $ogSiteName }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_AR' : 'en_US' }}">
    <meta property="og:title" content="{{ $seoTitle ?? $ogSiteName }}">
    @if (!empty($seoDescription))
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl ?? url()->current() }}">
    @if ($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
    @endif

    <meta name="twitter:card" content="{{ $resolvedOgImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seoTitle ?? $ogSiteName }}">
    @if (!empty($seoDescription))
        <meta name="twitter:description" content="{{ $seoDescription }}">
    @endif
    @if ($resolvedOgImage)
        <meta name="twitter:image" content="{{ $resolvedOgImage }}">
    @endif

    @foreach (config('languages.available', []) as $code => $meta)
        <link rel="alternate" hreflang="{{ $code }}"
            href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}">
    @endforeach

    @php
        $theme = \App\Models\SiteSetting::branding();
        $fonts = config('fonts', []);
        $headingFont = $fonts[$theme['heading_font']];
        $bodyFont = $fonts[$theme['body_font']];
        $primaryShades = \App\Support\ColorPalette::shades($theme['primary']);
        $secondaryShades = \App\Support\ColorPalette::shades($theme['secondary']);
        $accentShades = \App\Support\ColorPalette::shades($theme['accent']);
        $buttonHoverShade = \App\Support\ColorPalette::shades($theme['button_color'])[700];
    @endphp

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family={{ $headingFont['google'] }}&family={{ $bodyFont['google'] }}&display=swap"
        rel="stylesheet">

    @vite(['resources/css/site.css', 'resources/js/site.js'])

    <style>
        :root {
            @foreach ($primaryShades as $stop => $value)
                --color-warm-{{ $stop }}: {{ $value }};
            @endforeach
            --color-clay-50: {{ $secondaryShades[50] }};
            --color-clay-600: {{ $secondaryShades[600] }};
            --color-clay-700: {{ $secondaryShades[700] }};
            --color-accent-50: {{ $accentShades[50] }};
            --color-accent-600: {{ $accentShades[600] }};
            --color-accent-700: {{ $accentShades[700] }};
            --font-display: '{{ $headingFont['label'] }}',
            {{ $headingFont['fallback'] }};
            --font-sans: '{{ $bodyFont['label'] }}',
            {{ $bodyFont['fallback'] }};
            --btn-bg: {{ $theme['button_color'] }};
            --btn-bg-hover: {{ $buttonHoverShade }};
            --btn-text: {{ $theme['button_text_color'] }};
        }
    </style>

    @if (!empty($customCss))
        <style>
            {!! $customCss !!}
        </style>
    @endif

    @if ($headerScripts = \App\Models\SiteSetting::get('custom_header_scripts'))
        {!! $headerScripts !!}
    @endif
    @if (!empty($customHeadScripts))
        {!! $customHeadScripts !!}
    @endif
</head>

<body class="bg-warm-50 text-warm-950 antialiased">
    @if ($gtmId ?? null)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0"
                width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    <header class="sticky top-0 z-50 border-b border-warm-200/60 bg-warm-50/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}"
                class="flex min-w-0 items-center gap-2 font-display text-lg font-semibold text-warm-800 sm:text-xl">
                @if ($siteLogoUrl = \App\Models\SiteSetting::siteLogoUrl())
                    <img src="{{ $siteLogoUrl }}" alt="{{ \App\Models\SiteSetting::siteName() }}"
                        class="h-8 w-auto max-w-40 object-contain sm:h-9">
                @else
                    <span class="text-2xl">🐴</span>
                    <span class="truncate">{{ \App\Models\SiteSetting::siteName() }}</span>
                @endif
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-warm-800 lg:flex lg:flex-1 xl:gap-8"
                aria-label="{{ __('Primary') }}">
                @php($headerMenu = \App\Models\CmsMenu::query()->where('location', 'header')->with('items.children')->first())
                @forelse(($headerMenu?->items ?? collect()) as $item)
                    @if ($item->children->isNotEmpty())
                        <div class="group relative">
                            <a href="{{ $item->resolvedUrl() }}" target="{{ $item->target }}"
                                class="flex items-center gap-1 whitespace-nowrap transition hover:text-warm-600">
                                {{ $item->label }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition group-hover:rotate-180"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </a>
                            <div
                                class="invisible absolute start-0 top-full z-20 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                <div class="min-w-48 rounded-xl border border-warm-200/60 bg-warm-50 py-2 shadow-lg">
                                    @foreach ($item->children as $child)
                                        <a href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}"
                                            class="block whitespace-nowrap px-4 py-2 text-sm text-warm-800 transition hover:bg-warm-200/60 hover:text-warm-600">{{ $child->label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->resolvedUrl() }}" target="{{ $item->target }}"
                            class="{{ $item->is_button ? 'btn-warm !py-2 !px-5 text-sm' : 'whitespace-nowrap transition hover:text-warm-600' }}">{{ $item->label }}</a>
                    @endif
                @empty
                    <a href="{{ url('/') }}" class="whitespace-nowrap transition hover:text-warm-600">{{ __('Home') }}</a>
                    <a href="{{ url('/blog') }}" class="whitespace-nowrap transition hover:text-warm-600">{{ __('Stories') }}</a>
                @endforelse
                <x-language-switcher class="ms-auto" />
            </nav>

            <div class="flex items-center gap-2 lg:hidden">
                <x-language-switcher />
                <button type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="mobile-menu"
                    aria-label="{{ __('Toggle menu') }}"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-warm-200 text-warm-800 transition hover:bg-warm-200/60">
                    <svg data-icon-open xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg data-icon-close xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div data-mobile-menu-backdrop class="hidden fixed inset-0 z-40 bg-warm-950/40"></div>

        <div id="mobile-menu" data-mobile-menu class="border-t border-warm-200/60 bg-warm-50">
            <nav class="flex flex-col gap-1 px-4 py-4" aria-label="{{ __('Mobile') }}">
                @forelse(($headerMenu?->items ?? collect()) as $item)
                    @if ($item->children->isNotEmpty())
                        <details class="group rounded-xl">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between rounded-xl px-3 py-3 text-base font-medium text-warm-800 transition hover:bg-warm-200/60">
                                {{ $item->label }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition group-open:rotate-180"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </summary>
                            <div class="ms-3 flex flex-col gap-1 border-s border-warm-200/60 py-1 ps-3">
                                @foreach ($item->children as $child)
                                    <a href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}"
                                        class="rounded-xl px-3 py-2 text-sm text-warm-800 transition hover:bg-warm-200/60">{{ $child->label }}</a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a href="{{ $item->resolvedUrl() }}" target="{{ $item->target }}"
                            class="{{ $item->is_button ? 'btn-warm mt-2 w-full justify-center' : 'rounded-xl px-3 py-3 text-base font-medium text-warm-800 transition hover:bg-warm-200/60' }}">{{ $item->label }}</a>
                    @endif
                @empty
                    <a href="{{ url('/') }}"
                        class="rounded-xl px-3 py-3 text-base font-medium text-warm-800 transition hover:bg-warm-200/60">{{ __('Home') }}</a>
                    <a href="{{ url('/blog') }}"
                        class="rounded-xl px-3 py-3 text-base font-medium text-warm-800 transition hover:bg-warm-200/60">{{ __('Stories') }}</a>
                @endforelse
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-warm-200/60 bg-warm-100/60">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <div class="grid gap-10 md:grid-cols-3">
                <div>
                    <p class="font-display text-lg font-semibold text-warm-800">
                        {{ \App\Models\SiteSetting::siteName() }}
                    </p>
                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-warm-900/70">
                        {!! \App\Models\SiteSetting::footerText() ?:
                            __('Connecting horses and the people who love them with safe, caring journeys — every time.') !!}
                    </p>
                </div>

                <div>
                    <p class="section-eyebrow">{{ __('Contact') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-warm-900/80">
                        @if ($phone = \App\Models\SiteSetting::get('contact_phone'))
                            <li><a dir="ltr" href="tel:{{ $phone }}"
                                    class="hover:text-warm-600">{{ $phone }}</a></li>
                        @endif
                        @if ($email = \App\Models\SiteSetting::get('contact_email'))
                            <li><a href="mailto:{{ $email }}"
                                    class="hover:text-warm-600">{{ $email }}</a></li>
                        @endif
                    </ul>

                    @php($socialLinks = collect([
                        'facebook' => \App\Models\SiteSetting::get('social_facebook_url'),
                        'instagram' => \App\Models\SiteSetting::get('social_instagram_url'),
                        'x' => \App\Models\SiteSetting::get('social_x_url'),
                        'linkedin' => \App\Models\SiteSetting::get('social_linkedin_url'),
                        'youtube' => \App\Models\SiteSetting::get('social_youtube_url'),
                        'tiktok' => \App\Models\SiteSetting::get('social_tiktok_url'),
                        'whatsapp' => \App\Models\SiteSetting::get('social_whatsapp_url'),
                    ])->filter())
                    @if ($socialLinks->isNotEmpty())
                        <ul class="mt-5 flex flex-wrap items-center gap-3">
                            @foreach ($socialLinks as $network => $url)
                                <li>
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                        aria-label="{{ ucfirst($network) }}"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-warm-200/70 text-warm-700 transition hover:bg-warm-600 hover:text-white">
                                        @switch($network)
                                            @case('facebook')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94Z" />
                                                </svg>
                                            @break

                                            @case('instagram')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.22.6 1.77 1.15.55.55.9 1.11 1.15 1.77.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 0 1-1.15 1.77 4.9 4.9 0 0 1-1.77 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.77-1.15 4.9 4.9 0 0 1-1.15-1.77c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.22 1.15-1.77A4.9 4.9 0 0 1 5.45.53C6.09.28 6.82.11 7.88.06 8.94.01 9.28 0 12 0Zm0 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 8.25a3.25 3.25 0 1 1 0-6.5 3.25 3.25 0 0 1 0 6.5ZM17.5 4.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Z" />
                                                </svg>
                                            @break

                                            @case('x')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M18.24 2h3.06l-6.69 7.65L22.5 22h-6.17l-4.83-6.32L5.96 22H2.9l7.16-8.19L1.5 2h6.32l4.37 5.78L18.24 2Zm-1.08 18.17h1.7L7.9 3.75H6.08l11.08 16.42Z" />
                                                </svg>
                                            @break

                                            @case('linkedin')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.38-1.85 3.62 0 4.29 2.38 4.29 5.47v6.27ZM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45Z" />
                                                </svg>
                                            @break

                                            @case('youtube')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.51 3.5 12 3.5 12 3.5s-7.51 0-9.38.55A3.02 3.02 0 0 0 .5 6.19 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.81 3.02 3.02 0 0 0 2.12 2.14c1.87.55 9.38.55 9.38.55s7.51 0 9.38-.55a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.81ZM9.6 15.6V8.4l6.27 3.6-6.27 3.6Z" />
                                                </svg>
                                            @break

                                            @case('tiktok')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M16.6 5.82a4.4 4.4 0 0 1-3.08-1.27V15.3a5.1 5.1 0 1 1-4.4-5.05v2.55a2.55 2.55 0 1 0 1.8 2.44V2h2.5a4.4 4.4 0 0 0 3.18 4.22V5.82Z" />
                                                </svg>
                                            @break

                                            @case('whatsapp')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    class="h-4 w-4" fill="currentColor">
                                                    <path
                                                        d="M12.01 2C6.49 2 2 6.49 2 12.01c0 1.94.55 3.75 1.5 5.3L2 22l4.83-1.46a9.96 9.96 0 0 0 5.18 1.45h.01c5.52 0 10-4.49 10-10.01C22 6.49 17.53 2 12.01 2Zm0 18.18c-1.62 0-3.13-.44-4.43-1.2l-.32-.19-3.03.92.93-2.95-.2-.32a8.15 8.15 0 0 1-1.28-4.43c0-4.5 3.66-8.16 8.34-8.16 4.47 0 8.13 3.66 8.13 8.16 0 4.5-3.66 8.17-8.14 8.17Zm4.47-6.1c-.24-.12-1.44-.71-1.66-.79-.22-.08-.39-.12-.55.12-.16.24-.63.79-.78.95-.14.16-.28.18-.53.06-.24-.12-1.03-.38-1.96-1.21-.72-.64-1.21-1.44-1.36-1.68-.14-.24-.02-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.42-.55-.42h-.47c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.13 3.64.58.25 1.03.4 1.38.51.58.18 1.11.16 1.53.1.47-.07 1.44-.59 1.64-1.16.2-.57.2-1.05.14-1.16-.06-.11-.22-.17-.46-.29Z" />
                                                </svg>
                                            @break
                                        @endswitch
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <p class="section-eyebrow">{{ __('Explore') }}</p>
                    @php($footerMenu = \App\Models\CmsMenu::query()->where('location', 'footer')->with('items.children')->first())
                    <ul class="mt-3 space-y-2 text-sm text-warm-900/80">
                        @forelse(($footerMenu?->items ?? collect()) as $item)
                            <li>
                                <a href="{{ $item->resolvedUrl() }}"
                                    class="hover:text-warm-600">{{ $item->label }}</a>
                                @if ($item->children->isNotEmpty())
                                    <ul class="mt-2 space-y-2 ps-4">
                                        @foreach ($item->children as $child)
                                            <li><a href="{{ $child->resolvedUrl() }}"
                                                    class="hover:text-warm-600">{{ $child->label }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @empty
                            <li><a href="{{ url('/transfer-board') }}"
                                    class="hover:text-warm-600">{{ __('Transfer Board') }}</a></li>
                            <li><a href="{{ url('/blog') }}" class="hover:text-warm-600">{{ __('Stories') }}</a>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-4 border-t border-warm-200/60 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-warm-900/50">
                    &copy; {{ now()->year }} {{ \App\Models\SiteSetting::siteName() }}.
                    {{ __('All rights reserved.') }}
                </p>
                @php($legalMenu = \App\Models\CmsMenu::query()->where('location', 'footer_legal')->with('items')->first())
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-xs text-warm-900/60">
                    @forelse(($legalMenu?->items ?? collect()) as $item)
                        <li><a href="{{ $item->resolvedUrl() }}" class="hover:text-warm-600">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ url('/privacy-policy') }}" class="hover:text-warm-600">{{ __('Privacy Policy') }}</a></li>
                        <li><a href="{{ url('/terms-and-conditions') }}" class="hover:text-warm-600">{{ __('Terms & Conditions') }}</a></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </footer>

    @if ($bodyScripts = \App\Models\SiteSetting::get('custom_body_scripts'))
        {!! $bodyScripts !!}
    @endif
    @if (!empty($customBodyScripts))
        {!! $customBodyScripts !!}
    @endif

</body>

</html>
