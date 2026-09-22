@use('App\Support\NotificationText')
<x-mail::message>
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="text-align: {{ $rtl ? 'right' : 'left' }}">

# {{ $order->customer_name ? NotificationText::get('mail.greeting', ['name' => $order->customer_name]) : NotificationText::get('mail.greeting_guest') }}

{{ NotificationText::get('mail.refunded.intro', ['amount' => $amount, 'service' => $serviceName]) }}

<x-mail::panel>
{{ __('orders.order_number') }}: **{{ $order->order_number }}**
</x-mail::panel>

{{ NotificationText::get('mail.refunded.timing') }}

@if($keptFee)
{{ NotificationText::get('mail.refunded.fee_kept', ['amount' => $keptFee]) }}
@endif

<x-mail::button :url="$url">
{{ NotificationText::get('mail.track_button') }}
</x-mail::button>

</div>
</x-mail::message>
