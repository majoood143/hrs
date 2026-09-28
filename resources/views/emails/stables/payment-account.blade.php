@php $data = ['stable' => $account->stable?->name, 'gateway' => $account->gateway->label()]; @endphp
<x-mail::message>
# {{ __($key.'.heading', $data) }}

{{ __($key.'.intro', $data) }}

@if(filled($account->last_test_message))
**{{ __('stable_panel.payments.last_test') }}:** {{ $account->last_test_ok ? '✅' : '⚠️' }} {{ $account->last_test_message }}
@endif

@if($account->status === 'rejected' && filled($account->review_note))
**{{ __('stable_panel.fields.reason') }}:** {{ $account->review_note }}
@endif

<x-mail::button :url="$url">
{{ __($key.'.button') }}
</x-mail::button>
</x-mail::message>
