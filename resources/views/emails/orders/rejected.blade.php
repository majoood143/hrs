@use('App\Support\NotificationText')
<x-mail::message>
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="text-align: {{ $rtl ? 'right' : 'left' }}">

# {{ $order->customer_name ? NotificationText::get('mail.greeting', ['name' => $order->customer_name]) : NotificationText::get('mail.greeting_guest') }}

{{ NotificationText::get('mail.rejected.intro', ['service' => $serviceName]) }}

<x-mail::panel>
{{ __('orders.order_number') }}: **{{ $order->order_number }}**
</x-mail::panel>

@if($reason)
**{{ NotificationText::get('mail.rejected.reason') }}**

{{ $reason }}
@endif

@if($refund)
{{ NotificationText::get('mail.rejected.refund', ['amount' => $refund]) }}
@endif

<x-mail::button :url="$url">
{{ NotificationText::get('mail.track_button') }}
</x-mail::button>

</div>
</x-mail::message>
