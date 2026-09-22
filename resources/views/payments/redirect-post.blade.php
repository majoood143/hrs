<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('payments.redirecting') }}</title>
    <style>body{font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#faf7f2;color:#333}main{text-align:center;padding:2rem}button{margin-top:1rem;padding:.6rem 1.4rem;border:0;border-radius:.6rem;background:#05602b;color:#fff;font-size:1rem;cursor:pointer}</style>
</head>
<body>
    <main>
        <p>{{ __('payments.redirecting') }}</p>
        {{-- The browser must POST to the gateway itself; the form submits on load. --}}
        <form id="gateway-redirect" method="POST" action="{{ $redirect->url }}">
            @foreach($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript><button type="submit">{{ __('payments.continue') }}</button></noscript>
        </form>
    </main>
    <script>document.getElementById('gateway-redirect').submit();</script>
</body>
</html>
