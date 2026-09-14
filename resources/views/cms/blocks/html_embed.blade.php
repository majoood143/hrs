@php($boxed = ($data['container'] ?? 'boxed') !== 'full')

<section class="{{ $boxed ? 'mx-auto max-w-5xl px-6' : '' }} py-6">
    {!! $data['code'] ?? '' !!}
</section>
