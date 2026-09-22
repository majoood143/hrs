<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-2xl px-6 py-14">
        <p class="section-eyebrow">{{ __('payments.checkout_eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900">{{ __('payments.checkout_title') }}</h1>
        <p class="mt-2 text-sm text-warm-700">{{ __('orders.order_number') }}: <span class="font-semibold" dir="ltr">{{ $order->order_number }}</span></p>

        @if(session('error'))
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <div class="card-warm mt-8 p-6">
            @include('site.orders._breakdown')
        </div>

        @if(count($gateways) === 0)
            <p class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('payments.no_gateways') }}</p>
        @else
            <form method="POST" action="{{ route('payment.begin', $order->order_number) }}" class="mt-6 space-y-3">
                @csrf
                <p class="text-sm font-semibold text-warm-900">{{ count($gateways) > 1 ? __('payments.pay_with') : __('payments.pay_securely') }}</p>
                @foreach($gateways as $gateway)
                    <button type="submit" name="gateway" value="{{ $gateway->gateway()->value }}" class="btn-warm w-full justify-center">
                        {{ __('payments.pay_button', ['gateway' => $gateway->gateway()->label()]) }}
                    </button>
                @endforeach
            </form>
        @endif

        <p class="mt-6 text-xs text-warm-700">{{ __('payments.secure_note') }}</p>
    </div>
</x-layouts.site>
