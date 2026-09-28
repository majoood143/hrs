<x-mail::message>
# {{ __('stable_statement.mail.'.$settlement->direction.'.heading', ['amount' => $amount]) }}

{{ __('stable_statement.mail.'.$settlement->direction.'.intro', ['amount' => $amount, 'stable' => $settlement->stable?->name, 'date' => $settlement->paid_on->toDateString()]) }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('stable_statement.fields.amount') }} | **{{ $amount }}** |
| {{ __('stable_statement.fields.paid_on') }} | {{ $settlement->paid_on->toDateString() }} |
| {{ __('stable_statement.fields.method') }} | {{ __('stable_statement.methods.'.$settlement->method) }} |
@if($settlement->reference)
| {{ __('stable_statement.fields.reference') }} | {{ str_replace('|', '\|', $settlement->reference) }} |
@endif
</x-mail::table>

{{ __('stable_statement.balance.'.$balanceKey, ['amount' => $balance]) }}

<x-mail::button :url="$url">
{{ __('stable_statement.mail.button') }}
</x-mail::button>
</x-mail::message>
