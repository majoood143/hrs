{{-- Above an order form: what the customer will pay, before they fill it in. --}}
<div class="card-warm mb-6 p-5 text-start">
    @if($free)
        <p class="text-sm font-semibold text-warm-900">{{ $order->service->localizedName() }} · {{ __('orders.free_service') }}</p>
    @else
        <p class="text-xs font-semibold uppercase tracking-wide text-warm-700">{{ __('orders.you_will_pay') }}</p>
        @include('site.orders._breakdown')
        <p class="mt-2 text-xs text-warm-700">{{ __('orders.pay_after_submit') }}</p>
    @endif
</div>
