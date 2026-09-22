<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-md px-6 py-16">
        <p class="section-eyebrow">{{ __('account.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('account.login_heading') }}</h1>
        <p class="mt-2 text-sm text-warm-700">{{ __('account.login_intro') }}</p>

        @if(session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        @unless($available)
            <p class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('account.sms_unavailable') }}</p>
        @else
            <form method="POST" action="{{ route('account.send') }}" class="card-warm mt-8 space-y-4 p-6">
                @csrf
                <div>
                    <label for="phone" class="block text-sm font-semibold text-warm-900">{{ __('account.phone_label') }}</label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" required autofocus
                           value="{{ old('phone') }}" placeholder="9123 4567"
                           class="mt-1 w-full rounded-xl border border-warm-200 bg-white px-4 py-3 text-start text-warm-900 focus:border-warm-500 focus:outline-none">
                    <p class="mt-1 text-xs text-warm-700">{{ __('account.phone_hint') }}</p>
                    @error('phone')
                        <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-warm w-full justify-center">{{ __('account.send_code') }}</button>
            </form>
        @endunless
    </div>
</x-layouts.site>
