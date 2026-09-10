@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';

    $siteName = config('app.name', 'HRS');
    $primary = '#05602b';
    $secondary = '#0da74c';
    $buttonColor = $primary;
    $buttonTextColor = '#ffffff';
    $headingFont = ['label' => 'Fraunces', 'google' => 'Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700', 'fallback' => 'Georgia, serif'];
    $bodyFont = ['label' => 'Inter', 'google' => 'Inter:wght@400;500;600;700', 'fallback' => 'ui-sans-serif, system-ui, sans-serif'];
    $logoUrl = null;

    try {
        $siteName = \App\Models\SiteSetting::siteName() ?: $siteName;
        $branding = \App\Models\SiteSetting::branding();
        $fonts = config('fonts', []);

        $primary = $branding['primary'];
        $secondary = $branding['secondary'];
        $buttonColor = $branding['button_color'];
        $buttonTextColor = $branding['button_text_color'];
        $headingFont = $fonts[$branding['heading_font']] ?? $headingFont;
        $bodyFont = $fonts[$branding['body_font']] ?? $bodyFont;

        if ($logoPath = \App\Models\SiteSetting::get('site_logo')) {
            $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath);
        }
    } catch (\Throwable $e) {
        // The database may be unreachable (e.g. during a 500) — fall back to the defaults above.
    }

    $buttonHoverColor = $secondary;
    try {
        $buttonHoverColor = \App\Support\ColorPalette::shades($buttonColor)[700] ?? $secondary;
    } catch (\Throwable $e) {
        // Fall back to the secondary brand color if shade generation fails.
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') - @yield('title', $siteName)</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ $headingFont['google'] }}&family={{ $bodyFont['google'] }}&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand: {{ $primary }};
            --brand-dark: {{ $secondary }};
            --btn-bg: {{ $buttonColor }};
            --btn-bg-hover: {{ $buttonHoverColor }};
            --btn-text: {{ $buttonTextColor }};
            --font-display: '{{ $headingFont['label'] }}', {{ $headingFont['fallback'] }};
            --font-sans: '{{ $bodyFont['label'] }}', {{ $bodyFont['fallback'] }};
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            background: #f9fafb;
            color: #1f2937;
            font-family: var(--font-sans);
        }

        .card {
            width: 100%;
            max-width: 30rem;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 45px -20px rgba(0, 0, 0, 0.15);
            padding: 2.75rem 2.25rem;
            text-align: center;
        }

        .logo {
            height: 2.75rem;
            margin: 0 auto 1.75rem;
            display: block;
        }

        .site-name {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.125rem;
            margin: 0 0 1.75rem;
            color: var(--brand);
        }

        .code {
            font-family: var(--font-display);
            font-size: 3.75rem;
            font-weight: 700;
            line-height: 1;
            color: var(--brand);
            margin: 0 0 0.5rem;
        }

        .heading {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            color: #111827;
        }

        .description {
            font-size: 0.95rem;
            color: #6b7280;
            margin: 0 0 1.75rem;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 0.65rem 1.75rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s ease, background-color 0.15s ease;
        }

        .btn-primary {
            background: var(--btn-bg);
            color: var(--btn-text);
        }

        .btn-primary:hover {
            opacity: 0.92;
            background: var(--btn-bg-hover);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="card">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="logo">
        @else
            <p class="site-name">{{ $siteName }}</p>
        @endif

        <p class="code">@yield('code')</p>
        <h1 class="heading">@yield('heading')</h1>
        <p class="description">@yield('description')</p>

        <div class="actions">
            @yield('actions')
        </div>
    </div>
</body>
</html>
