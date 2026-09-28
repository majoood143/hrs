<x-mail::message>
# {{ __('stable_panel.mail.registered.heading') }}

{{ __('stable_panel.mail.registered.intro') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('stable_panel.fields.en_name') }} | {{ str_replace('|', '\|', $stable->en_name) }} |
| {{ __('stable_panel.fields.ar_name') }} | {{ str_replace('|', '\|', $stable->ar_name) }} |
| {{ __('stable_panel.fields.city') }} | {{ $stable->city?->en_name }}{{ $stable->region ? ', '.$stable->region->en_name : '' }} |
| {{ __('stable_panel.fields.owner') }} | {{ str_replace('|', '\|', $owner->name) }} |
| {{ __('stable_panel.fields.email') }} | {{ $owner->email }} |
| {{ __('stable_panel.fields.phone') }} | {{ $owner->phone }} |
</x-mail::table>

<x-mail::button :url="$adminUrl">
{{ __('stable_panel.mail.registered.button') }}
</x-mail::button>
</x-mail::message>
