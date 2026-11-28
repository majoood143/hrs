{{-- 4 generations behind the horse: parents span 8 rows, then 4, 2, 1 (the sire's half on top). --}}
@php
    $tree = ['s' => $lot->sire, 'd' => $lot->dam] + array_merge(array_fill_keys(\App\Models\AuctionLot::PEDIGREE_KEYS, null), array_intersect_key($lot->pedigree ?? [], array_flip(\App\Models\AuctionLot::PEDIGREE_KEYS)));
    // only as many generations as have a name: a 2-generation pedigree is not padded with dashes
    $depth = max(1, collect($tree)->filter()->keys()->map(fn ($key) => strlen($key))->max() ?? 1);
    $columns = collect($tree)->keys()->filter(fn ($key) => strlen($key) <= $depth)->groupBy(fn ($key) => strlen($key));
    $rows = 2 ** $depth;
@endphp
@if(array_filter($tree))
    <section class="mt-12" aria-labelledby="pedigree-title">
        <h2 id="pedigree-title" class="flex items-center gap-2 font-display text-2xl font-semibold text-warm-900">
            <x-heroicon-o-share class="h-6 w-6 text-warm-600" aria-hidden="true" />{{ __('auctions.site.pedigree') }}
        </h2>
        <div class="mt-5 overflow-x-auto">
            <div class="grid grid-flow-col gap-1 text-xs {{ $depth > 2 ? 'min-w-[40rem]' : '' }}" style="grid-template-columns: repeat({{ $depth }}, minmax(0, 1fr)); grid-template-rows: repeat({{ $rows }}, minmax(1.75rem, auto))">
                @foreach($columns as $generation => $keys)
                    @foreach($keys as $key)
                        <div style="grid-row: span {{ 2 ** ($depth - $generation) }}" class="flex items-center rounded-md border-s-4 px-2 py-1 {{ $key[0] === 's' ? 'border-warm-700 bg-warm-100' : 'border-warm-300 bg-white' }}">
                            <span class="{{ $generation === 1 ? 'font-display text-base font-semibold' : 'font-medium' }} text-warm-900">{{ $tree[$key] ?: '—' }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>
@endif
