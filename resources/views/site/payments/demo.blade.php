<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-2xl px-6 py-14">
        <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-800">{{ __('payments.demo_badge') }}</span>
        <h1 class="mt-3 font-display text-3xl font-semibold text-warm-900">{{ __('payments.demo_title') }}</h1>
        <p class="mt-2 text-sm text-warm-700">{{ __('payments.demo_notice') }}</p>

        <div class="card-warm mt-8 p-6">
            @include('site.orders._breakdown')
        </div>

        <form method="POST" action="{{ route('payment.demo.decide', $order->order_number) }}" class="mt-6 flex flex-wrap gap-3">
            @csrf
            <button type="submit" name="decision" value="approve" class="btn-warm">{{ __('payments.demo_approve') }}</button>
            <button type="submit" name="decision" value="decline" class="btn-warm-outline">{{ __('payments.demo_decline') }}</button>
        </form>
    </div>
</x-layouts.site>
