<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-md px-6 py-16">
        <p class="section-eyebrow">{{ __('account.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('account.verify_heading') }}</h1>
        <p class="mt-2 text-sm text-warm-700">{{ __('account.verify_intro', ['phone' => $masked, 'minutes' => $ttl]) }}</p>

        @if(session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        @if($demoCode)
            {{-- Only under the "demo" SMS driver an admin switched on for a presentation: nothing was sent. --}}
            <div class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
                <span class="font-bold">{{ __('account.demo_badge') }}</span> {{ __('account.demo_code') }}
                <span class="font-mono text-lg font-bold" dir="ltr">{{ $demoCode }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('account.verify.check') }}" class="card-warm mt-8 space-y-4 p-6">
            @csrf
            <div>
                <label for="code" class="block text-sm font-semibold text-warm-900">{{ __('account.code_label') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" required autofocus maxlength="6"
                       pattern="[0-9]{6}" placeholder="123456"
                       class="mt-1 w-full rounded-xl border border-warm-200 bg-white px-4 py-3 text-center font-mono text-2xl tracking-[0.4em] text-warm-900 focus:border-warm-500 focus:outline-none">
                @error('code')
                    <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-warm w-full justify-center">{{ __('account.verify_button') }}</button>
        </form>

        <div class="mt-4 flex items-center justify-between text-sm">
            <form method="POST" action="{{ route('account.resend') }}">
                @csrf
                <button type="submit" class="font-semibold text-warm-700 underline hover:text-warm-900">{{ __('account.resend') }}</button>
            </form>
            <a href="{{ route('account.login') }}" class="text-warm-700 hover:text-warm-900">{{ __('account.change_phone') }}</a>
        </div>
    </div>
</x-layouts.site>
