@php
    $currency = \App\Models\SiteSetting::currency();
@endphp
@if($currency['icon_url'])
    <img src="{{ $currency['icon_url'] }}" alt="{{ $currency['code'] }}" {{ $attributes->merge(['class' => 'inline-block h-4 w-4 object-contain align-middle']) }}>
@else
    <span {{ $attributes }}>{{ $currency['symbol'] }}</span>
@endif
