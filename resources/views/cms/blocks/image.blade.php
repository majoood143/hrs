@php
    $alt = \App\Support\Localized::value($data, 'alt');
    $caption = \App\Support\Localized::value($data, 'caption');
@endphp

@if(!empty($data['image']))
<figure data-reveal class="mx-auto max-w-5xl px-6 py-10">
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($data['image']) }}" alt="{{ $alt ?? '' }}" class="w-full rounded-3xl object-cover shadow-lg shadow-warm-900/10">
    @if($caption)
        <figcaption class="mt-3 text-center text-sm text-warm-900/60">{{ $caption }}</figcaption>
    @endif
</figure>
@endif
