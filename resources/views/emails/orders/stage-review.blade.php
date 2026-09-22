<x-mail::message>
# {{ __('notifications.mail.stage.heading', ['number' => $order->order_number]) }}

{{ __('notifications.mail.stage.intro', ['stage' => $stage, 'service' => $serviceName]) }}

<x-mail::table>
| {{ __('packstub-form-builder::form-builder.mail.field') }} | {{ __('packstub-form-builder::form-builder.mail.value') }} |
|:--|:--|
| {{ __('admin_service_order.fields.customer_name') }} | {{ str_replace(['|', "\n"], ['\|', ' '], (string) $order->customer_name) ?: '—' }} |
@foreach ($rows as $row)
| {{ $row['label'] }} | {{ str_replace(['|', "\n"], ['\|', ' '], $row['value']) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$adminUrl">
{{ __('notifications.mail.stage.button') }}
</x-mail::button>
</x-mail::message>
