<x-layouts.site :seo-title="$seoTitle" :noindex="true">
    <div class="mx-auto max-w-3xl px-6 py-14">
        <a href="{{ route('account.orders') }}" class="text-sm font-semibold text-warm-600 hover:text-warm-800">&larr; {{ __('account.back_to_orders') }}</a>

        <p class="section-eyebrow mt-6">{{ __('orders.status_eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-warm-900" dir="ltr">{{ $order->order_number }}</h1>
        <p class="mt-1 text-sm text-warm-700">{{ $order->service?->localizedName() ?? __('orders.service') }} · <span dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</span></p>

        @if($order->isPaid() && $order->status === \App\Enums\OrderStatus::Cancelled)
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">{{ __('orders.paid_but_cancelled') }}</div>
        @endif

        @if($order->status === \App\Enums\OrderStatus::Rejected)
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <p class="font-semibold">{{ __('account.rejected_heading') }}</p>
                @if($order->rejectionReason())
                    <p class="mt-1 whitespace-pre-line">{{ $order->rejectionReason() }}</p>
                @endif
                @if($order->isPaid() && $order->refundableAmount() > 0)
                    <p class="mt-2">{{ __('account.refund_due', ['amount' => \App\Models\SiteSetting::currency()['code'].' '.number_format($order->refundableAmount(), 3)]) }}</p>
                @endif
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

        <div class="mt-6 flex flex-wrap gap-3">
            @if($order->isPayable())
                <a href="{{ route('payment.start', $order->order_number) }}" class="btn-warm">{{ __('orders.pay_now') }}</a>
            @endif
            @if($hasReceipt)
                <a href="{{ route('account.orders.receipt', $order->order_number) }}" class="btn-warm-outline">{{ __('account.download_receipt') }}</a>
                @if($order->receipt_number)
                    <span class="self-center text-sm text-warm-700">{{ __('orders.receipt_number') }}: <span class="font-semibold" dir="ltr">{{ $order->receipt_number }}</span></span>
                @endif
            @endif
        </div>

        <div class="card-warm mt-6 p-6">
            @include('site.orders._breakdown')
        </div>

        @if($order->refunds->isNotEmpty())
            <h2 class="mt-10 font-display text-xl font-semibold text-warm-900">{{ __('account.refunds') }}</h2>
            <ul class="card-warm mt-4 divide-y divide-warm-200 p-2 text-sm">
                @foreach($order->refunds as $refund)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <span class="text-warm-700" dir="ltr">{{ $refund->created_at->format('Y-m-d') }}</span>
                        <span class="font-semibold text-warm-900" dir="ltr">{{ $order->currency }} {{ number_format((float) $refund->amount, 3) }}</span>
                    </li>
                @endforeach
            </ul>
            @if((float) $order->fee_amount > 0)
                <p class="mt-2 text-xs text-warm-700">{{ __('account.fee_not_refunded') }}</p>
            @endif
        @endif

        @if($order->documents->isNotEmpty())
            <h2 class="mt-10 font-display text-xl font-semibold text-warm-900">{{ __('account.documents') }}</h2>
            <ul class="card-warm mt-4 divide-y divide-warm-200 p-2 text-sm">
                @foreach($order->documents as $document)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="font-medium text-warm-900">{{ $document->title }}</p>
                            <p class="text-xs text-warm-700" dir="ltr">{{ $document->humanSize() }}</p>
                        </div>
                        <a href="{{ route('account.orders.document', [$order->order_number, $document->id]) }}" class="btn-warm-outline">{{ __('account.download') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if(count($answers))
            <h2 class="mt-10 font-display text-xl font-semibold text-warm-900">{{ __('account.your_request') }}</h2>
            <dl class="card-warm mt-4 divide-y divide-warm-200 p-2 text-sm">
                @foreach($answers as $answer)
                    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
                        <dt class="font-semibold text-warm-700">{{ $answer['label'] }}</dt>
                        <dd class="whitespace-pre-line text-warm-900 sm:col-span-2">{{ $answer['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
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
