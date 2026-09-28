@php $key = "stable_bookings.mail.{$audience}_{$type}"; @endphp
<x-mail::message>
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="text-align: {{ $rtl ? 'right' : 'left' }}">

# {{ __($key.'.heading', $data) }}

{{ __($key.'.intro', $data + ['who' => __('stable_bookings.cancelled_by.'.($booking->cancellation_source ?: 'customer'))]) }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('stable_bookings.fields.reference') }} | **{{ $data['reference'] }}** |
| {{ __('stable_bookings.fields.session') }} | {{ $data['offering'] }} |
| {{ __('stable_bookings.fields.stable') }} | {{ $data['stable'] }} |
| {{ __('stable_bookings.fields.date') }} | {{ $data['date'] }} |
| {{ __('stable_bookings.fields.time') }} | {{ $data['time'] }} |
| {{ __('stable_bookings.fields.riders') }} | {{ $data['riders'] }}{{ $data['names'] ? ' · '.str_replace('|', '\|', $data['names']) : '' }} |
@if($audience === 'stable')
| {{ __('stable_bookings.fields.customer') }} | {{ str_replace('|', '\|', $data['customer']) }} · {{ $data['phone'] }} |
@endif
| {{ __('stable_bookings.fields.payment') }} | {{ $data['payment'] }} |
</x-mail::table>

@if($type === 'cancelled' && filled($data['reason']))
**{{ __('stable_bookings.fields.reason') }}:** {{ $data['reason'] }}
@endif

@if($type === 'cancelled' && $refundDue)
{{ __("stable_bookings.mail.{$audience}_refund") }}
@endif

@if($audience === 'customer' && $type === 'confirmed' && filled($data['map']))
[{{ __('stable_bookings.directions') }}]({{ $data['map'] }})
@endif

<x-mail::button :url="$audience === 'stable' ? $data['panel'] : $data['url']">
{{ __("stable_bookings.mail.{$audience}_button") }}
</x-mail::button>

</div>
</x-mail::message>
