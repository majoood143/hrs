{{-- Colour, year of birth and country, as the catalogue writes them: "Grey, 2015 (QA)". --}}
@php
    $facts = trim(implode(', ', array_filter([$stallion->colour_label, $stallion->foaled_year])).($stallion->origin ? ' ('.$stallion->origin.')' : ''));
@endphp
@if($facts !== '')
    <p class="{{ $class ?? '' }}"><span dir="ltr">{{ $facts }}</span></p>
@endif
