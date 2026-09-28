{{-- A 1–5 rating as stars (decorative) with the number as text. --}}
@props(['rating', 'count' => null, 'size' => 'h-4 w-4'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    <span class="inline-flex text-amber-500" aria-hidden="true">
        @foreach(range(1, 5) as $i)
            @if($rating >= $i - 0.25)
                <x-heroicon-s-star class="{{ $size }}" />
            @else
                <x-heroicon-o-star class="{{ $size }} text-warm-300" />
            @endif
        @endforeach
    </span>
    <span class="text-sm font-semibold text-warm-900">{{ number_format((float) $rating, 1) }}</span>
    @if($count !== null)
        <span class="text-xs text-warm-600">({{ trans_choice('stable_reviews.count', $count, ['count' => $count]) }})</span>
    @endif
</span>
