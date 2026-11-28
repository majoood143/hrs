@php
    $money = fn ($amount) => \App\Models\SiteSetting::currency()['code'].' '.\App\Models\SiteSetting::formatAmount((float) $amount);
    $vat = (float) $order->vat_on_price + (float) $order->vat_on_fee;
@endphp

<dl class="divide-y divide-warm-200 text-sm">
    <div class="flex items-center justify-between gap-4 py-3">
        <dt class="text-warm-700">{{ $order->serviceName() }}</dt>
        <dd class="font-semibold text-warm-900" dir="ltr">{{ $money($order->priceBeforeDiscount()) }}</dd>
    </div>
    @foreach($order->extraLines() as $line)
        <div class="flex items-center justify-between gap-4 py-3">
            <dt class="text-warm-700">{{ __('stable_extras.line', ['name' => $line->name, 'quantity' => $line->quantity]) }}</dt>
            <dd class="font-semibold text-warm-900" dir="ltr">{{ $money($line->amount) }}</dd>
        </div>
    @endforeach
    @if($order->hasDiscount())
        <div class="flex items-center justify-between gap-4 py-3">
            <dt class="text-emerald-700">{{ $order->discountLabel() }}</dt>
            <dd class="font-semibold text-emerald-700" dir="ltr">−{{ $money($order->discount_amount) }}</dd>
        </div>
    @endif
    @if((float) $order->fee_amount > 0)
        <div class="flex items-center justify-between gap-4 py-3">
            <dt class="text-warm-700">{{ __('orders.service_fee') }}</dt>
            <dd class="font-semibold text-warm-900" dir="ltr">{{ $money($order->fee_amount) }}</dd>
        </div>
    @endif
    @if($vat > 0)
        <div class="flex items-center justify-between gap-4 py-3">
            <dt class="text-warm-700">{{ __('orders.vat', ['rate' => rtrim(rtrim(number_format((float) $order->vat_rate, 2), '0'), '.')]) }}</dt>
            <dd class="font-semibold text-warm-900" dir="ltr">{{ $money($vat) }}</dd>
        </div>
    @endif
    <div class="flex items-center justify-between gap-4 py-3 text-base">
        <dt class="font-semibold text-warm-900">{{ __('orders.total') }}</dt>
        <dd class="font-display text-lg font-semibold text-warm-900" dir="ltr">{{ $money($order->total) }}</dd>
    </div>
</dl>
