<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-2xl px-6 py-14">
        <p class="section-eyebrow">{{ __('orders.status_eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900" dir="ltr">{{ $order->order_number }}</h1>

        @if(session('error'))
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if($order->isPaid() && $order->status === \App\Enums\OrderStatus::Cancelled)
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">
                {{ __('orders.paid_but_cancelled') }}
            </div>
        @endif

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="card-warm p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('orders.status_heading') }}</p>
                <p class="mt-1 font-display text-lg font-semibold text-warm-900">{{ $order->status->label() }}</p>
            </div>
            <div class="card-warm p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('orders.payment_heading') }}</p>
                <p class="mt-1 font-display text-lg font-semibold text-warm-900">{{ $order->payment_status->label() }}</p>
            </div>
        </div>

        @if($order->receipt_number)
            <p class="mt-4 text-sm text-warm-700">{{ __('orders.receipt_number') }}: <span class="font-semibold" dir="ltr">{{ $order->receipt_number }}</span></p>
        @endif

        <div class="card-warm mt-6 p-6">
            @include('site.orders._breakdown')
        </div>

        @if($order->isPaid())
            <p class="mt-4 text-sm text-warm-700">
                <a href="{{ route('account.login') }}" class="font-semibold underline hover:text-warm-900">{{ __('account.sign_in_for_receipt') }}</a>
            </p>
        @endif

        @if($order->isPayable())
            <a href="{{ route('payment.start', $order->order_number) }}" class="btn-warm mt-6">{{ __('orders.pay_now') }}</a>
        @endif

        @if($events->isNotEmpty())
            <h2 class="mt-10 font-display text-xl font-semibold text-warm-900">{{ __('orders.timeline') }}</h2>
            <ol class="mt-4 space-y-3 border-warm-200 ps-4 text-sm" style="border-inline-start-width:2px">
                @foreach($events as $event)
                    <li>
                        <p class="font-medium text-warm-900">{{ $event->message ?: __('orders.events.'.$event->type) }}</p>
                        <p class="text-xs text-warm-700" dir="ltr">{{ $event->created_at->format('Y-m-d H:i') }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-layouts.site>
