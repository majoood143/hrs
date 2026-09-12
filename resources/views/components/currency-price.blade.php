@props(['amount', 'decimals' => 3])

@php
    $formatted = number_format((float) $amount, $decimals);
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    <x-currency-symbol />{{ $formatted }}
</span>
