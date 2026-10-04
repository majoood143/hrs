{{--
    A small picture of one pattern on its own area, for the pattern buttons. Its colours are the
    designer's CSS variables (--silks-{area}-base / -accent), so every button follows the colours
    the visitor picked without repainting each one.
--}}
@props(['area', 'svg' => '', 'uid'])

@php
    use App\Support\Silks\SilksTemplate as T;

    [$w, $h] = T::BOXES[$area];
    $shape = $area === 'cap' ? T::CAP_CROWN : T::clipPath($area);
@endphp

<svg viewBox="{{ T::thumbViewBox($area) }}" dir="ltr" aria-hidden="true" focusable="false" {{ $attributes }}>
    <defs><clipPath id="{{ $uid }}"><path d="{{ T::clipPath($area) }}"/></clipPath></defs>
    @if($area === 'cap')
        <path d="{{ T::CAP_PEAK }}" style="fill: var(--silks-cap-base)" stroke="#44403c" stroke-width="3" stroke-linejoin="round"/>
    @endif
    <g clip-path="url(#{{ $uid }})">
        <rect x="-20" y="-20" width="{{ $w + 40 }}" height="{{ $h + 40 }}" style="fill: var(--silks-{{ $area }}-base)"/>
        <g fill="currentColor" style="color: var(--silks-{{ $area }}-accent)">{!! $svg !!}</g>
    </g>
    <path d="{{ $shape }}" fill="none" stroke="#44403c" stroke-width="3" stroke-linejoin="round"/>
</svg>
