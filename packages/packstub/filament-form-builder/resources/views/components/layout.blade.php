<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite('resources/css/site.css')
    <style>
        body { margin: 0; font-family: var(--fb-font, var(--font-sans, ui-sans-serif, system-ui, sans-serif)); background: var(--fb-color-background, var(--color-warm-50, #f8fafc)); color: var(--fb-color-text, var(--color-warm-950, #0f172a)); }
        .fb-page { max-width: 40rem; margin: 0 auto; padding: 3rem 1.25rem; }
        .fb-page__title { font-family: var(--font-display, inherit); font-size: 1.875rem; font-weight: 700; margin: 0 0 0.5rem; }
        .fb-page__description { margin: 0 0 2rem; color: var(--fb-color-muted, var(--color-warm-700, #475569)); }
    </style>
</head>
<body>
    <main class="fb-page">
        {{ $slot }}
    </main>
</body>
</html>
