@use('App\Support\NotificationText')
<x-mail::message>
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="text-align: {{ $rtl ? 'right' : 'left' }}">

# {{ $order->customer_name ? NotificationText::get('mail.greeting', ['name' => $order->customer_name]) : NotificationText::get('mail.greeting_guest') }}

{{ $order->isPaid() ? NotificationText::get('mail.received.intro_paid', ['service' => $serviceName]) : NotificationText::get('mail.received.intro_free', ['service' => $serviceName]) }}

<x-mail::panel>
{{ __('orders.order_number') }}: **{{ $order->order_number }}**
</x-mail::panel>

@if($order->isPaid())
<x-mail::table>
| {{ __('receipt.description') }} | {{ __('receipt.amount') }} |
|:--|--:|
| {{ $serviceName }} | {{ $money($order->price) }} |
@if((float) $order->fee_amount > 0)
| {{ __('orders.service_fee') }} | {{ $money($order->fee_amount) }} |
@endif
@if($order->vatAmount() > 0)
| {{ __('orders.vat', ['rate' => rtrim(rtrim(number_format((float) $order->vat_rate, 2), '0'), '.')]) }} | {{ $money($order->vatAmount()) }} |
@endif
| **{{ __('receipt.total_paid') }}** | **{{ $money($order->total) }}** |
</x-mail::table>

@if($order->receipt_number)
{{ __('orders.receipt_number') }}: {{ $order->receipt_number }}. {{ NotificationText::get('mail.received.receipt_attached') }}
@endif
@endif

{{ NotificationText::get('mail.received.next') }}

<x-mail::button :url="$url">
{{ NotificationText::get('mail.track_button') }}
</x-mail::button>

</div>
</x-mail::message>
