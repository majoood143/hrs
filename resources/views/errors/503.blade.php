@php
    $langs = config('languages.available', ['en' => ['native' => 'English', 'dir' => 'ltr']]);
    $default = config('languages.default', 'en');

    $locale = request()->query('lang');
    if (! is_string($locale) || ! array_key_exists($locale, $langs)) {
        $locale = $default;
    }

    $dir = $langs[$locale]['dir'] ?? 'ltr';

    $siteName = config('app.name', 'HRS');
    $primary = '#05602b';
    $secondary = '#0da74c';
    $logoUrl = null;

    try {
        $siteName = \App\Models\SiteSetting::siteName() ?: $siteName;
        $branding = \App\Models\SiteSetting::branding();
        $primary = $branding['primary'] ?? $primary;
        $secondary = $branding['secondary'] ?? $secondary;

        if ($logoPath = \App\Models\SiteSetting::get('site_logo')) {
            $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath);
        }
    } catch (\Throwable $e) {
        // Database may be unreachable during the outage — fall back to defaults above.
    }

    $message = null;
    try {
        if (app()->isDownForMaintenance()) {
            $payload = app(\Illuminate\Contracts\Foundation\MaintenanceMode::class)->data();
            $message = $payload['message'][$locale] ?? null;
        }
    } catch (\Throwable $e) {
        // Ignore — fall back to the default copy below.
    }

    $messages = [
        'en' => [
            'eyebrow' => 'Scheduled Maintenance',
            'default' => "We're currently performing scheduled maintenance and will be back shortly. Thank you for your patience.",
            'refresh' => 'This page will refresh automatically.',
        ],
        'ar' => [
            'eyebrow' => 'صيانة مجدولة',
            'default' => 'نقوم حاليًا بأعمال صيانة مجدولة وسنعود قريبًا. نشكركم على تفهمكم.',
            'refresh' => 'سيتم تحديث هذه الصفحة تلقائيًا.',
        ],
    ];
    $copy = $messages[$locale] ?? $messages['en'];
    $message = $message ?: $copy['default'];

    $refreshSeconds = null;
    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $refreshSeconds = $exception->getHeaders()['Refresh'] ?? null;
    }
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($refreshSeconds)
        <meta http-equiv="refresh" content="{{ (int) $refreshSeconds }}">
    @endif
    <title>{{ $siteName }} — {{ $copy['eyebrow'] }}</title>

    <style>
        :root {
            --primary: {{ $primary }};
            --secondary: {{ $secondary }};
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            background: linear-gradient(160deg, color-mix(in srgb, var(--primary) 10%, white), #ffffff 55%);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1f2937;
        }

        .card {
            width: 100%;
            max-width: 30rem;
            text-align: center;
            background: #ffffff;
            border-radius: 1.25rem;
            padding: 2.75rem 2.25rem;
            box-shadow: 0 20px 45px -20px rgba(0, 0, 0, 0.25);
        }

        .logo {
            max-height: 44px;
            margin: 0 auto 1.25rem;
            display: block;
        }

        .site-name {
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .icon {
            width: 3.25rem;
            height: 3.25rem;
            margin: 0 auto 1.25rem;
            border-radius: 9999px;
            background: color-mix(in srgb, var(--secondary) 18%, white);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 1.75rem;
            height: 1.75rem;
            color: var(--secondary);
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--secondary);
            margin: 0 0 0.5rem;
        }

        .message {
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
            margin: 0;
        }

        .lang-switch {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
        }

        .lang-switch a {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            text-decoration: none;
            color: #6b7280;
        }

        .lang-switch a.active {
            background: color-mix(in srgb, var(--primary) 12%, white);
            color: var(--primary);
            font-weight: 600;
        }

        @if ($refreshSeconds)
            .refresh-hint {
                margin-top: 1rem;
                font-size: 0.75rem;
                color: #9ca3af;
            }
        @endif
    </style>
</head>

<body>
    <main class="card">
        @if ($logoUrl)
            <img class="logo" src="{{ $logoUrl }}" alt="{{ $siteName }}">
        @else
            <p class="site-name">{{ $siteName }}</p>
        @endif

        <div class="icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437 5.877 5.877" />
            </svg>
        </div>

        <p class="eyebrow">{{ $copy['eyebrow'] }}</p>
        <p class="message">{{ $message }}</p>

        <div class="lang-switch">
            @foreach ($langs as $code => $meta)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                    class="{{ $code === $locale ? 'active' : '' }}">{{ $meta['native'] ?? strtoupper($code) }}</a>
            @endforeach
        </div>

        @if ($refreshSeconds)
            <p class="refresh-hint">{{ $copy['refresh'] }}</p>
        @endif
    </main>
</body>

</html>
