<x-mail::message>
# {{ __('stable_panel.mail.reviewed.'.$status.'.heading', ['stable' => $stable->name]) }}

{{ __('stable_panel.mail.reviewed.'.$status.'.intro', ['stable' => $stable->name]) }}

@if ($status === 'approved' && $stable->formatted_commission)
**{{ __('stable_panel.fields.commission') }}:** {{ $stable->formatted_commission }}
@endif

@if ($status !== 'approved' && filled($stable->rejection_reason))
**{{ __('stable_panel.fields.reason') }}:** {{ $stable->rejection_reason }}
@endif

<x-mail::button :url="$panelUrl">
{{ __('stable_panel.mail.reviewed.button') }}
</x-mail::button>
</x-mail::message>
