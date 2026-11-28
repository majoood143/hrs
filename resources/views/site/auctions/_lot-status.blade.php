{{-- A lot's state as a badge: open, not open yet, sold, not sold, withdrawn. --}}
@php
    $key = match (true) {
        $lot->status === 'ready' && $lot->isOpenAt(now()) => 'open',
        $lot->status === 'ready' => 'soon',
        default => $lot->status,
    };
    $tones = [
        'open' => ['bg-emerald-50 text-emerald-700 ring-emerald-100', 'heroicon-s-signal'],
        'soon' => ['bg-amber-50 text-amber-700 ring-amber-100', 'heroicon-o-clock'],
        'sold' => ['bg-warm-900 text-white ring-warm-200', 'heroicon-o-check-badge'],
        'unsold' => ['bg-warm-50 text-warm-700 ring-warm-100', 'heroicon-o-x-circle'],
        'withdrawn' => ['bg-rose-50 text-rose-700 ring-rose-100', 'heroicon-o-no-symbol'],
    ];
    [$tone, $icon] = $tones[$key] ?? $tones['unsold'];
@endphp
<span data-lot-badge class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-4 {{ $tone }} {{ $class ?? '' }}">
    <x-dynamic-component :component="$icon" class="h-3.5 w-3.5" aria-hidden="true" />{{ __('auctions.site.lot_states.'.$key) }}
</span>
