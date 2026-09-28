<x-mail::message>
# {{ __('stable_packages.mail.'.$audience.'.heading', $data) }}

{{ __('stable_packages.mail.'.$audience.'.intro', $data) }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('stable_packages.fields.reference') }} | **{{ $data['reference'] }}** |
| {{ __('stable_packages.fields.package') }} | {{ $data['package'] }} |
| {{ __('stable_bookings.fields.stable') }} | {{ $data['stable'] }} |
| {{ __('stable_packages.fields.sessions') }} | {{ $data['sessions'] }} |
| {{ __('stable_packages.fields.expires') }} | {{ $data['date'] }} |
@if($audience === 'stable')
| {{ __('stable_bookings.fields.customer') }} | {{ str_replace('|', '\|', $data['customer']) }} · {{ $data['phone'] }} |
@endif
</x-mail::table>

<x-mail::button :url="$url">
{{ __('stable_packages.mail.'.$audience.'.button') }}
</x-mail::button>
</x-mail::message>
