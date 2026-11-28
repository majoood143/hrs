@php
    $styles = [
        'live' => ['bg-emerald-500 text-white', 'heroicon-s-signal'],
        'preview' => ['bg-amber-400 text-amber-950', 'heroicon-o-eye'],
        'ended' => ['bg-white/90 text-warm-900', 'heroicon-o-check-badge'],
    ];
    [$tone, $icon] = $styles[$phase] ?? $styles['ended'];
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $tone }} {{ $class ?? '' }}">
    <x-dynamic-component :component="$icon" class="h-4 w-4" aria-hidden="true" />{{ __('auctions.site.phases.'.$phase) }}
</span>
