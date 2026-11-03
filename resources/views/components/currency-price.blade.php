@props(['amount'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    <x-currency-symbol />{{ \App\Models\SiteSetting::formatAmount($amount) }}
</span>
