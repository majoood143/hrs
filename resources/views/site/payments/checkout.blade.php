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
            <form method="POST" action="{{ route('payment.begin', $order->order_number) }}" class="mt-6 space-y-3" id="pay-form">
                @csrf
                <p class="text-sm font-semibold text-warm-900">{{ count($gateways) > 1 ? __('payments.pay_with') : __('payments.pay_securely') }}</p>
                @foreach($gateways as $gateway)
                    <button type="submit" name="gateway" value="{{ $gateway->gateway()->value }}" class="btn-warm w-full justify-center">
                        {{ __('payments.pay_button', ['gateway' => $gateway->gateway()->label()]) }}
                    </button>
                @endforeach
            </form>
            @if($watchesPayment ?? false)
                <div id="pay-waiting" class="mt-6 rounded-2xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-900" role="status" hidden>
                    <p class="font-semibold">{{ __('payments.waiting_title') }}</p>
                    <p class="mt-1">{{ __('payments.waiting_body') }}</p>
                </div>
                {{-- Thawani's page does not always send the customer back after a payment and cannot be framed: it opens in its own window while this page watches the order --}}
                <script>
                    (() => {
                        const form = document.getElementById('pay-form');
                        const waiting = document.getElementById('pay-waiting');
                        const isSettled = () => fetch(@json(route('payment.status', $order->order_number)), { headers: { Accept: 'application/json' } })
                            .then((response) => response.json()).then((status) => status.settled).catch(() => false);
                        const moveOn = () => window.location.assign(@json(route('payment.start', $order->order_number)));

                        // the browser's Back shows this page from its own cache, without asking the server
                        window.addEventListener('pageshow', async () => { if (await isSettled()) moveOn(); });

                        form.addEventListener('submit', (event) => {
                            if (event.submitter?.value !== 'thawani') return;

                            const payment = window.open('', 'thawani-checkout');

                            // popups blocked: pay in this tab; coming back to this address settles the order
                            if (!payment) return;

                            form.target = 'thawani-checkout';
                            setTimeout(() => { form.hidden = true; waiting.hidden = false; });

                            const timer = setInterval(async () => {
                                if (await isSettled()) {
                                    clearInterval(timer);
                                    payment.close();
                                    moveOn();
                                } else if (payment.closed) {
                                    clearInterval(timer);
                                    form.removeAttribute('target');
                                    form.hidden = false;
                                    waiting.hidden = true;
                                }
                            }, 4000);
                        });
                    })();
                </script>
            @endif
            @if($autoStart ?? false)
                {{-- opened from the app with one way to pay: go straight on, the app already showed the total --}}
                <script>document.getElementById('pay-form').querySelector('button[type=submit]').click();</script>
            @endif
        @endif

        <p class="mt-6 text-xs text-warm-700">{{ __('payments.cards_note') }} {{ __('payments.secure_note') }}</p>
    </div>
</x-layouts.site>
