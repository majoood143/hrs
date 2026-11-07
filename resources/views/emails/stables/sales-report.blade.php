<x-mail::message>
# {{ __('stable_statement.mail.sales_report.heading', ['stable' => $stable->name]) }}

{{ __('stable_statement.mail.sales_report.intro', ['period' => $period]) }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('stable_statement.cards.bookings') }} | **{{ $totals['bookings'] }}** |
| {{ __('stable_statement.cards.collected') }} | {{ $money($totals['collected']) }} |
| {{ __('stable_statement.mail.sales_report.refunded') }} | {{ $money($totals['refunded']) }} |
| {{ __('stable_statement.cards.commission') }} | {{ $money($totals['commission']) }} |
| {{ __('stable_statement.cards.stable_share') }} | **{{ $money($totals['stable_share']) }}** |
</x-mail::table>

{{ __('stable_statement.balance.'.$balanceKey, ['amount' => $money(abs($balance))]) }}

<x-mail::button :url="$url">
{{ __('stable_statement.mail.button') }}
</x-mail::button>
</x-mail::message>
