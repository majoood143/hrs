{{-- One area's colours and pattern, clipped to its shape, in the area's local box. --}}
@php([$w, $h] = \App\Support\Silks\SilksTemplate::BOXES[$area])
<g clip-path="url(#{{ $uid }}-{{ $area }})">
    <rect x="-20" y="-20" width="{{ $w + 40 }}" height="{{ $h + 40 }}" fill="{{ $paint['base'] }}" data-silks-base="{{ $area }}"/>
    <g fill="currentColor" color="{{ $paint['accent'] }}" data-silks-pattern="{{ $area }}" data-silks-key="{{ $paint['pattern'] }}">{!! $paint['svg'] !!}</g>
    <rect x="-20" y="-20" width="{{ $w + 40 }}" height="{{ $h + 40 }}" fill="url(#{{ $uid }}-shade)" pointer-events="none"/>
</g>
