@props(['postedAt' => null, 'views' => null])

{{-- Post date + view count under a listing's title (views are counted by Support\ViewCounter). --}}
<div {{ $attributes->class(['flex flex-wrap items-center gap-2 text-xs font-semibold text-warm-700']) }}>
    @if($postedAt)
        <span class="inline-flex items-center gap-1.5 rounded-full bg-warm-100 px-3 py-1">
            <x-heroicon-o-calendar-days class="h-4 w-4 text-warm-500" aria-hidden="true" />
            <span>{{ __('listings.posted_on') }}</span>
            <time datetime="{{ $postedAt->toDateString() }}" title="{{ $postedAt->diffForHumans() }}">{{ $postedAt->translatedFormat('j F Y') }}</time>
        </span>
    @endif

    @if($views !== null)
        <span class="inline-flex items-center gap-1.5 rounded-full bg-warm-100 px-3 py-1">
            <x-heroicon-o-eye class="h-4 w-4 text-warm-500" aria-hidden="true" />
            <span>{{ trans_choice('listings.views', (int) $views, ['count' => number_format((int) $views)]) }}</span>
        </span>
    @endif
</div>
